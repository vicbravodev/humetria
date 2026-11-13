<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\OutOfZoneRule;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Support\CompanyPolicy;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

mutates(CompanyPolicy::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

/** @return array<string, mixed> */
function policyRules(AttendancePolicy $policy): array
{
    return $policy->only(['out_of_zone', 'max_accuracy_m', 'require_qr_onsite', 'trusted_may_use_browser', 'retention_months', 'late_tolerance_minutes']);
}

it('gives the defaults while RH has not saved a policy: the same the table writes', function (): void {
    $defaults = policyRules(CompanyPolicy::current());
    expect(AttendancePolicy::query()->count())->toBe(0);

    // A row with nothing but its company: the table's defaults fill the rest.
    DB::table('attendance_policies')->insert(['id' => Str::uuid()->toString(), 'tenant_id' => $this->tenant->id, 'created_at' => now(), 'updated_at' => now()]);

    expect($defaults)->toBe(policyRules(AttendancePolicy::query()->sole()))
        ->and($defaults['out_of_zone'])->toBe(OutOfZoneRule::Block);
});

it('gives the policy RH saved', function (): void {
    AttendancePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'out_of_zone' => OutOfZoneRule::AllowAndFlag, 'max_accuracy_m' => 30]);

    expect(CompanyPolicy::current())->out_of_zone->toBe(OutOfZoneRule::AllowAndFlag)->max_accuracy_m->toBe(30);
});

it('never gives another company\'s policy', function (): void {
    AttendancePolicy::factory()->create(['tenant_id' => Tenant::factory()->create()->id, 'max_accuracy_m' => 30]);

    expect(CompanyPolicy::current()->max_accuracy_m)->toBe(100);
});
