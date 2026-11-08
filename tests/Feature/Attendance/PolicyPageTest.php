<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Attendance\Enums\OutOfZoneRule;
use App\Modules\Attendance\Http\Controllers\PolicyController;
use App\Modules\Attendance\Http\Requests\PolicyRequest;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia;

mutates(PolicyController::class, PolicyRequest::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/** The policy as the form sends it. */
function policyInput(array $overrides = []): array
{
    return ['out_of_zone' => 'allow_and_flag', 'max_accuracy_m' => '80', 'require_qr_onsite' => '1', 'trusted_may_use_browser' => '0', 'retention_months' => '24', ...$overrides];
}

function savePolicy(array $input): Illuminate\Testing\TestResponse
{
    return test()->from(route('tenant.attendance.policy.show', 'acme'))->put(route('tenant.attendance.policy.update', 'acme'), $input);
}

describe('the page', function (): void {
    it('shows the defaults while RH has not saved a policy: block out of zone, 100 m, QR on site, trusted browser, 12 months', function (): void {
        $this->actingAs($this->hr);

        $this->get(route('tenant.attendance.policy.show', 'acme'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('attendance/policy')
            ->where('policy', ['outOfZone' => 'block', 'maxAccuracyM' => 100, 'requireQrOnsite' => true, 'trustedMayUseBrowser' => true, 'retentionMonths' => 12])
            ->where('saved', false)
            ->where('canManage', true));
        expect(AttendancePolicy::query()->count())->toBe(0);
    });

    it('shows the policy saved, read-only for a viewer', function (): void {
        AttendancePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'out_of_zone' => OutOfZoneRule::AllowAndFlag, 'max_accuracy_m' => 50, 'require_qr_onsite' => false, 'trusted_may_use_browser' => false, 'retention_months' => 36]);
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        $this->get(route('tenant.attendance.policy.show', 'acme'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('policy', ['outOfZone' => 'allow_and_flag', 'maxAccuracyM' => 50, 'requireQrOnsite' => false, 'trustedMayUseBrowser' => false, 'retentionMonths' => 36])
            ->where('saved', true)
            ->where('canManage', false));
    });

    it('does not show another company\'s policy', function (): void {
        $globex = Tenant::factory()->create();
        AttendancePolicy::factory()->create(['tenant_id' => $globex->id, 'retention_months' => 60]);
        workIn($this->tenant);
        $this->actingAs($this->hr);

        $this->get(route('tenant.attendance.policy.show', 'acme'))->assertInertia(fn (AssertableInertia $page) => $page->where('policy.retentionMonths', 12)->where('saved', false));
    });

    it('keeps the committee out and answers 404 to a union', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Committee));
        $this->get(route('tenant.attendance.policy.show', 'acme'))->assertForbidden();

        $union = Tenant::factory()->union()->create();
        $this->actingAs(memberOf($union, TenantRole::Owner));
        $this->get(route('tenant.attendance.policy.show', $union->slug))->assertNotFound();
        $this->put(route('tenant.attendance.policy.update', $union->slug), policyInput())->assertNotFound();
    });
});

describe('saving it', function (): void {
    it('saves the one policy of the company, to the audit trail, and tells RH', function (): void {
        $this->actingAs($this->hr);

        savePolicy(policyInput())->assertRedirect(route('tenant.attendance.policy.show', 'acme'))
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Política guardada. Aplica desde la siguiente marca.']);
        savePolicy(policyInput(['retention_months' => '18', 'require_qr_onsite' => '0']))->assertSessionHasNoErrors();

        expect(AttendancePolicy::query()->sole())
            ->out_of_zone->toBe(OutOfZoneRule::AllowAndFlag)
            ->max_accuracy_m->toBe(80)
            ->require_qr_onsite->toBeFalse()
            ->trusted_may_use_browser->toBeFalse()
            ->retention_months->toBe(18)
            ->and(AuditEntry::query()->where('event', 'attendance.policy_saved')->count())->toBe(2);
    });

    it('takes a switch left off as no', function (): void {
        $this->actingAs($this->hr);

        savePolicy(array_diff_key(policyInput(), ['require_qr_onsite' => true, 'trusted_may_use_browser' => true]))->assertSessionHasNoErrors();

        expect(AttendancePolicy::query()->sole())->require_qr_onsite->toBeFalse()->trusted_may_use_browser->toBeFalse();
    });

    it('takes the exact limits of the accuracy and of the retention', function (array $input): void {
        $this->actingAs($this->hr);

        savePolicy(policyInput($input))->assertSessionHasNoErrors();
    })->with([
        '10 m' => [['max_accuracy_m' => '10']],
        '500 m' => [['max_accuracy_m' => '500']],
        '12 months' => [['retention_months' => '12']],
        '120 months' => [['retention_months' => '120']],
    ]);

    it('is checked in Spanish', function (array $input, string $field, string $message): void {
        $this->actingAs($this->hr);

        savePolicy(policyInput($input))->assertSessionHasErrors([$field => $message]);
        expect(AttendancePolicy::query()->count())->toBe(0);
    })->with([
        'accuracy under 10 m' => [['max_accuracy_m' => '9'], 'max_accuracy_m', 'El campo precisión máxima debe estar entre 10 y 500.'],
        'accuracy over 500 m' => [['max_accuracy_m' => '501'], 'max_accuracy_m', 'El campo precisión máxima debe estar entre 10 y 500.'],
        'retention of 11 months' => [['retention_months' => '11'], 'retention_months', 'El campo meses de conservación debe estar entre 12 y 120.'],
        'retention not a number' => [['retention_months' => 'doce'], 'retention_months', 'El campo meses de conservación debe ser un número entero.'],
        'an unknown rule' => [['out_of_zone' => 'ignore'], 'out_of_zone', 'Elige «Bloquear» o «Permitir y señalar».'],
        'no rule' => [['out_of_zone' => ''], 'out_of_zone', 'El campo fuera de zona es obligatorio.'],
        'a switch that is not yes or no' => [['require_qr_onsite' => 'tal vez'], 'require_qr_onsite', 'El campo QR en el centro debe ser verdadero o falso.'],
    ]);

    it('cannot be done by a viewer', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        savePolicy(policyInput())->assertForbidden();
        expect(AttendancePolicy::query()->count())->toBe(0);
    });
});
