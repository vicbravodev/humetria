<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Attendance\Actions\SaveCenterMode;
use App\Modules\Attendance\Enums\CenterMode;
use App\Modules\Attendance\Http\Controllers\CenterModeController;
use App\Modules\Attendance\Http\Requests\CenterModeRequest;
use App\Modules\Attendance\Models\AttendanceCenterMode;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Log;

mutates(SaveCenterMode::class, CenterModeController::class, CenterModeRequest::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Obra Norte']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

function centerModeUrl(WorkCenter $center): string
{
    return route('tenant.attendance.centers.mode', ['acme', $center->id]);
}

it('puts a center in the field and back on the company\'s policy, one row per center', function (): void {
    $this->actingAs($this->hr);

    $this->put(centerModeUrl($this->plant), ['mode' => 'field'])->assertRedirect()->assertSessionHasNoErrors();
    expect(AttendanceCenterMode::query()->sole())->work_center_id->toBe($this->plant->id)->mode->toBe(CenterMode::Field);

    $this->put(centerModeUrl($this->plant), ['mode' => 'company'])->assertRedirect();
    expect(AttendanceCenterMode::query()->sole()->mode)->toBe(CenterMode::Company);
});

it('writes each change to the audit trail and the log, with ids and the mode only', function (): void {
    Log::spy();
    $this->actingAs($this->hr);

    $this->put(centerModeUrl($this->plant), ['mode' => 'field']);

    $entry = AuditEntry::query()->where('event', 'attendance.center_mode_saved')->sole();
    expect($entry->metadata)->toEqual(['work_center_id' => $this->plant->id, 'mode' => 'field']);
    Log::shouldHaveReceived('info')->once()->with('attendance.center_mode_saved', ['work_center_id' => $this->plant->id, 'mode' => 'field']);
});

it('refuses a mode it does not know', function (string $mode): void {
    $this->actingAs($this->hr);

    $this->put(centerModeUrl($this->plant), ['mode' => $mode])->assertSessionHasErrors('mode');
    expect(AttendanceCenterMode::query()->count())->toBe(0);
})->with(['remote', '', 'FIELD']);

it('cannot be changed by a viewer', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

    $this->put(centerModeUrl($this->plant), ['mode' => 'field'])->assertForbidden();
    expect(AttendanceCenterMode::query()->count())->toBe(0);
});

it('answers 404 for a center archived or of another company, and leaves theirs as it was', function (): void {
    $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();
    $globex = Tenant::factory()->create();
    $theirCenter = WorkCenter::factory()->for($globex)->create();
    $theirMode = AttendanceCenterMode::factory()->create(['tenant_id' => $globex->id, 'work_center_id' => $theirCenter->id, 'mode' => CenterMode::Company]);
    workIn($this->tenant);
    $this->actingAs($this->hr);

    $this->put(centerModeUrl($archived), ['mode' => 'field'])->assertNotFound();
    $this->put(centerModeUrl($theirCenter), ['mode' => 'field'])->assertNotFound();

    workIn($globex);
    expect($theirMode->fresh()?->mode)->toBe(CenterMode::Company)
        ->and(AttendanceCenterMode::query()->count())->toBe(1);
});
