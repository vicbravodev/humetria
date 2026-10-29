<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Actions\ArchiveWorkCenter;
use App\Modules\Tenancy\Actions\RestoreWorkCenter;
use App\Modules\Tenancy\Http\Controllers\WorkCenterController;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;

mutates(ArchiveWorkCenter::class, RestoreWorkCenter::class, WorkCenterController::class);

it('lives at /admin/{slug}/work-centers/{id}/archive and /restore', function (): void {
    expect(route('tenant.work-centers.archive', ['tenant' => 'acme', 'workCenter' => 'abc'], absolute: false))->toBe('/admin/acme/work-centers/abc/archive')
        ->and(route('tenant.work-centers.restore', ['tenant' => 'acme', 'workCenter' => 'abc'], absolute: false))->toBe('/admin/acme/work-centers/abc/restore');
});

it('sends guests to the login', function (string $action): void {
    $workCenter = WorkCenter::factory()->create();

    $this->post(route("tenant.work-centers.{$action}", [$workCenter->tenant, $workCenter->id]))->assertRedirect(route('login'));
})->with(['archive', 'restore']);

it('archives a work center without deleting it', function (): void {
    $this->freezeSecond();
    $workCenter = WorkCenter::factory()->create();
    Log::spy();

    $this->actingAs(ownerOf($workCenter->tenant))
        ->post(route('tenant.work-centers.archive', [$workCenter->tenant, $workCenter->id]))
        ->assertRedirect(route('tenant.work-centers.index', $workCenter->tenant))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Centro de trabajo archivado.']]);

    expect(reloaded($workCenter)->archived_at?->equalTo(now()))->toBeTrue()
        ->and(AuditEntry::query()->count())->toBe(0);

    Log::shouldHaveReceived('info')->once()->with('work_center.archived', ['work_center_id' => $workCenter->id]);
});

it('restores an archived work center', function (): void {
    $workCenter = WorkCenter::factory()->archived()->create();
    Log::spy();

    $this->actingAs(ownerOf($workCenter->tenant))
        ->post(route('tenant.work-centers.restore', [$workCenter->tenant, $workCenter->id]))
        ->assertRedirect(route('tenant.work-centers.index', $workCenter->tenant))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Centro de trabajo restaurado.']]);

    expect(reloaded($workCenter)->archived_at)->toBeNull()
        ->and(AuditEntry::query()->count())->toBe(0);

    Log::shouldHaveReceived('info')->once()->with('work_center.restored', ['work_center_id' => $workCenter->id]);
});

it('answers 409 to archiving an archived one or restoring an active one, and changes nothing', function (): void {
    $this->freezeSecond();
    $archived = WorkCenter::factory()->state(['archived_at' => now()->subDay()])->create();
    $active = WorkCenter::factory()->for($archived->tenant)->create();
    $owner = ownerOf($archived->tenant);

    $this->actingAs($owner)->post(route('tenant.work-centers.archive', [$archived->tenant, $archived->id]))->assertConflict();
    $this->actingAs($owner)->post(route('tenant.work-centers.restore', [$active->tenant, $active->id]))->assertConflict();

    expect(reloaded($archived)->archived_at?->equalTo(now()->subDay()))->toBeTrue()
        ->and(reloaded($active)->archived_at)->toBeNull();
});

it('answers 404 to a work center of another company', function (string $action, bool $archived): void {
    $foreign = WorkCenter::factory()->state(['archived_at' => $archived ? now() : null])->create();
    $tenant = Tenant::factory()->create();

    $this->actingAs(ownerOf($tenant))
        ->post(route("tenant.work-centers.{$action}", [$tenant, $foreign->id]))
        ->assertNotFound();

    expect(reloaded($foreign)->archived_at === null)->toBe(! $archived);
})->with(['archive' => ['archive', false], 'restore' => ['restore', true]]);

it('refuses a suspended company with 423', function (): void {
    $workCenter = WorkCenter::factory()->for(Tenant::factory()->deactivated())->create();

    $this->actingAs(ownerOf($workCenter->tenant))
        ->post(route('tenant.work-centers.archive', [$workCenter->tenant, $workCenter->id]))
        ->assertStatus(423);

    expect(reloaded($workCenter)->archived_at)->toBeNull();
});
