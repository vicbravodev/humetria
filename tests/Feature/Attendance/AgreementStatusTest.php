<?php

declare(strict_types=1);

use App\Modules\Attendance\Models\AgreementAcceptance;
use App\Modules\Attendance\Models\AttendanceAgreement;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Support\AgreementStatus;
use App\Modules\Attendance\Support\CurrentAgreement;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    $this->bruno = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
});

it('gives the version of the current agreement a person accepted', function (): void {
    agreementAccepted($this->ana->id);

    expect(resolve(AgreementStatus::class)->acceptedVersion($this->ana->id))->toBe(resolve(CurrentAgreement::class)->get()->version);
});

it('gives nothing to someone who accepted only an older version', function (): void {
    AttendancePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'retention_months' => 24]);
    agreementAccepted($this->ana->id);
    AttendancePolicy::query()->sole()->update(['retention_months' => 36]);

    expect(AgreementAcceptance::query()->count())->toBe(1)
        ->and(resolve(CurrentAgreement::class)->get()->version)->toBe(2)
        ->and(resolve(AgreementStatus::class)->acceptedVersion($this->ana->id))->toBeNull()
        ->and(AttendanceAgreement::query()->count())->toBe(2);
});

it('gives nothing to someone who accepted nothing, nor because of another person\'s acceptance', function (): void {
    agreementAccepted($this->bruno->id);

    expect(resolve(AgreementStatus::class)->acceptedVersion($this->ana->id))->toBeNull();
});

/** `$employeeId` accepts the agreement `$agreementId` now. */
function acceptedOn(string $employeeId, string $agreementId): void
{
    AgreementAcceptance::query()->create([
        'tenant_id' => test()->tenant->id,
        'agreement_id' => $agreementId,
        'employee_id' => $employeeId,
        'credential_id' => WorkerCredential::factory()->create(['tenant_id' => test()->tenant->id, 'employee_id' => $employeeId])->id,
        'accepted_at' => now(),
    ]);
}

describe('acceptedAt', function (): void {
    // Version 1 written on 2 November and version 2 on 9 November: the second text changes with the retention.
    beforeEach(function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00', 'UTC'));
        AttendancePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'retention_months' => 24]);
        $this->first = resolve(CurrentAgreement::class)->get();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 12:00:00', 'UTC'));
        AttendancePolicy::query()->sole()->update(['retention_months' => 36]);
        $this->second = resolve(CurrentAgreement::class)->get();
        $this->status = resolve(AgreementStatus::class);
    });

    it('counts version 1 for a ticket issued before version 2 existed', function (): void {
        acceptedOn($this->ana->id, $this->first->id);

        expect($this->status->acceptedAt($this->ana->id, CarbonImmutable::parse('2026-11-08 12:00:00', 'UTC')))->toBeTrue();
    });

    it('does not count version 1 for a ticket issued once version 2 was current', function (): void {
        acceptedOn($this->ana->id, $this->first->id);

        expect($this->status->acceptedAt($this->ana->id, CarbonImmutable::parse('2026-11-09 12:00:00', 'UTC')))->toBeFalse()
            ->and($this->status->acceptedAt($this->ana->id, CarbonImmutable::parse('2026-11-10 12:00:00', 'UTC')))->toBeFalse();
    });

    it('counts version 2 whenever the ticket was issued', function (): void {
        acceptedOn($this->ana->id, $this->second->id);

        expect($this->status->acceptedAt($this->ana->id, CarbonImmutable::parse('2026-11-08 12:00:00', 'UTC')))->toBeTrue()
            ->and($this->status->acceptedAt($this->ana->id, CarbonImmutable::parse('2026-11-12 12:00:00', 'UTC')))->toBeTrue();
    });

    it('counts any acceptance for a ticket issued before every version, and none for no acceptance', function (): void {
        acceptedOn($this->ana->id, $this->second->id);
        $before = CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC');

        expect($this->status->acceptedAt($this->ana->id, $before))->toBeTrue()
            ->and($this->status->acceptedAt($this->bruno->id, $before))->toBeFalse()
            ->and($this->status->acceptedAt($this->bruno->id, CarbonImmutable::parse('2026-11-12 12:00:00', 'UTC')))->toBeFalse();
    });

    it('does not count what another person accepted', function (): void {
        acceptedOn($this->bruno->id, $this->second->id);

        expect($this->status->acceptedAt($this->ana->id, CarbonImmutable::parse('2026-11-12 12:00:00', 'UTC')))->toBeFalse();
    });
});
