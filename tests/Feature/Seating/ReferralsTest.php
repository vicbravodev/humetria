<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Seating\Actions\RecordReferral;
use App\Modules\Seating\Http\Controllers\ReferralController;
use App\Modules\Seating\Models\MedicalReferral;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(RecordReferral::class, ReferralController::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->position = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Ruiz']);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id]);
    $this->assessment = RiskAssessment::factory()->signed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id, 'work_center_id' => $this->center->id]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

function referralUrl(): string
{
    return route('tenant.seating.referrals.store', [test()->tenant, test()->assessment->id]);
}

it('records a referral of the person of an assessment, with its date, and shows it on the assessment', function (): void {
    $this->actingAs($this->hr);

    $this->post(referralUrl(), ['referred' => true, 'referred_on' => '2026-02-28', 'note' => 'Se le sugirió ir a medicina del trabajo.'])->assertRedirect()->assertSessionHasNoErrors();

    $referral = MedicalReferral::query()->sole();
    expect($referral->employee_id)->toBe($this->ana->id)->and($referral->referred)->toBeTrue()->and($referral->referred_on->toDateString())->toBe('2026-02-28')
        ->and($referral->created_by)->toBe($this->hr->id);
    $this->get(route('tenant.seating.assessments.show', [$this->tenant, $this->assessment->id]))
        ->assertInertia(fn (Assert $page) => $page->where('referrals.0.referred', true)->where('referrals.0.note', 'Se le sugirió ir a medicina del trabajo.')->where('referrals.0.referredOn', '2026-02-28'));
});

it('records «evaluated, no referral» as such', function (): void {
    $this->actingAs($this->hr);

    $this->post(referralUrl(), ['referred' => false, 'referred_on' => '2026-03-01', 'note' => 'Evaluada, sin canalización.'])->assertSessionHasNoErrors();

    expect(MedicalReferral::query()->sole()->referred)->toBeFalse();
});

it('keeps the note out of the log and the audit trail', function (): void {
    Log::spy();
    $this->actingAs($this->hr);

    $this->post(referralUrl(), ['referred' => true, 'referred_on' => '2026-03-01', 'note' => 'Lumbalgia crónica'])->assertSessionHasNoErrors();

    $referral = MedicalReferral::query()->sole();
    $record = ['referral_id' => $referral->id, 'work_center_id' => $this->center->id];
    expect(AuditEntry::query()->where('event', 'seating.referral_recorded')->sole()->metadata)->toEqual($record)
        ->and(json_encode(AuditEntry::query()->get()->toArray()))->not->toContain('Lumbalgia');
    Log::shouldHaveReceived('info')->with('seating.referral_recorded', $record)->once();
    Log::shouldNotHaveReceived('info', fn ($message, $context = []): bool => str_contains(json_encode($context), 'Lumbalgia'));
});

it('rejects a future date, a missing answer and a long note', function (array $bad, string $field): void {
    $this->actingAs($this->hr);

    $this->post(referralUrl(), [...['referred' => true, 'referred_on' => '2026-03-01', 'note' => null], ...$bad])->assertSessionHasErrors($field);

    expect(MedicalReferral::query()->count())->toBe(0);
})->with([
    'tomorrow' => [['referred_on' => '2026-03-02'], 'referred_on'],
    'no date' => [['referred_on' => null], 'referred_on'],
    'no answer' => [['referred' => null], 'referred'],
    'long note' => [['note' => str_repeat('a', 1001)], 'note'],
]);

it('lets only RH and the Comisión of the center record them: never the viewer, never another center', function (): void {
    $viewer = memberOf($this->tenant, TenantRole::Viewer);
    $cora = memberOf($this->tenant, TenantRole::Committee);
    $elsewhere = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $cora->id]);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id, 'user_id' => $elsewhere->id]);
    $form = ['referred' => true, 'referred_on' => '2026-03-01'];

    $this->actingAs($viewer)->post(referralUrl(), $form)->assertForbidden();
    $this->actingAs($elsewhere)->post(referralUrl(), $form)->assertNotFound();
    expect(MedicalReferral::query()->count())->toBe(0);

    $this->actingAs($cora)->post(referralUrl(), $form)->assertSessionHasNoErrors();
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner))->post(referralUrl(), $form)->assertSessionHasNoErrors();
    expect(MedicalReferral::query()->count())->toBe(2);
});

it('gives the viewer no referral anywhere: a 403 on the assessment that lists them', function (): void {
    MedicalReferral::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->center->id, 'note' => 'Nota privada']);

    $response = $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(route('tenant.seating.assessments.show', [$this->tenant, $this->assessment->id]));

    $response->assertForbidden();
    expect($response->getContent())->not->toContain('Nota privada');
});

it('does not read the referrals of another company', function (): void {
    MedicalReferral::factory()->create();
    workIn($this->tenant);

    expect(MedicalReferral::query()->count())->toBe(0);
    $this->actingAs($this->hr)->post(route('tenant.seating.referrals.store', [$this->tenant, RiskAssessment::factory()->create()->id]), ['referred' => true, 'referred_on' => '2026-03-01'])->assertNotFound();
});
