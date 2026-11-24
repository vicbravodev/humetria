<?php

declare(strict_types=1);

use App\Modules\Attendance\Models\AgreementAcceptance;
use App\Modules\Attendance\Models\AttendanceAgreement;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Support\AgreementStatus;
use App\Modules\Attendance\Support\CurrentAgreement;
use App\Modules\Tenancy\Models\Tenant;
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
