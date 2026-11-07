<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\SaveZone;
use App\Modules\Attendance\Exceptions\WorkCenterNotAvailable;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Support\Facades\Log;

mutates(SaveZone::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->center = WorkCenterFactory::new()->createOne(['tenant_id' => $this->tenant->id]);
});

it('saves a zone in a work center, logging ids only', function (): void {
    Log::spy();

    $zone = resolve(SaveZone::class)->handle($this->center->id, 'Edificio A', 19.4326, -99.1332, 60);

    expect($zone->fresh())
        ->work_center_id->toBe($this->center->id)
        ->name->toBe('Edificio A')
        ->latitude->toBe(19.4326)
        ->longitude->toBe(-99.1332)
        ->radius_m->toBe(60);
    Log::shouldHaveReceived('info')->once()->with('attendance.zone_saved', ['zone_id' => $zone->id, 'work_center_id' => $this->center->id]);
});

it('updates a zone of the company', function (): void {
    $zone = resolve(SaveZone::class)->handle($this->center->id, 'Edificio A', 19.4, -99.1, 60);

    $again = resolve(SaveZone::class)->handle($this->center->id, 'Edificio B', 19.5, -99.2, 80, $zone->id);

    expect($again->id)->toBe($zone->id)
        ->and(AttendanceZone::query()->sole())->name->toBe('Edificio B')->radius_m->toBe(80);
});

it('does not update a zone of another company', function (): void {
    $zoneOfB = AttendanceZone::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    expect(fn () => resolve(SaveZone::class)->handle($this->center->id, 'X', 19.4, -99.1, 60, $zoneOfB->id))
        ->toThrow(WorkCenterNotAvailable::class);
});

it('does not save a zone in an archived or foreign work center', function (): void {
    $archived = WorkCenterFactory::new()->archived()->createOne(['tenant_id' => $this->tenant->id]);
    $foreign = WorkCenterFactory::new()->createOne(['tenant_id' => Tenant::factory()->create()->id]);

    expect(fn () => resolve(SaveZone::class)->handle($archived->id, 'X', 19.4, -99.1, 60))->toThrow(WorkCenterNotAvailable::class)
        ->and(fn () => resolve(SaveZone::class)->handle($foreign->id, 'X', 19.4, -99.1, 60))->toThrow(WorkCenterNotAvailable::class)
        ->and(AttendanceZone::query()->count())->toBe(0);
});
