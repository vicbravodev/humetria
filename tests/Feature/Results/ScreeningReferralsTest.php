<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Organization\Models\Employee;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Results\Actions\RecordScreeningReferral;
use App\Modules\Results\Enums\Destination;
use App\Modules\Results\Enums\HowTold;
use App\Modules\Results\Enums\ReferralStatus;
use App\Modules\Results\Http\Controllers\ScreeningReferralController;
use App\Modules\Results\Http\Requests\ScreeningReferralRequest;
use App\Modules\Results\Models\ScreeningReferral;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ScreeningReferralController::class, ScreeningReferralRequest::class, RecordScreeningReferral::class, ReferralStatus::class, Destination::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->campaign = Campaign::factory()->trauma()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $this->screening = TraumaScreening::factory()->create(['campaign_id' => $this->campaign->id, 'requires_clinical_assessment' => true]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

function screeningReferralUrl(string $campaignId, string $employeeId): string
{
    return route('tenant.campaigns.screenings.update', [test()->tenant, $campaignId, $employeeId]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function screeningReferralForm(array $overrides = []): array
{
    return ['destination' => 'imss', 'told' => 'in_person', 'referred_on' => '2026-11-18', 'note' => 'Cita en la UMF 12 el lunes', 'remind' => '1', ...$overrides];
}

// Rediseño v2 (board V4-N035-Canalizacion, decisión A7): the record says where the person was channeled (or that they did
// not want care), how they were told and a note without clinical detail, with a reminder at 30 days; never an answer or a
// diagnosis. It replaces the status and the free-text institution of the first version.
it('records where the person was channeled, how they were told, the day and a note, with a reminder at 30 days', function (TenantRole $role): void {
    $user = memberOf($this->tenant, $role);

    $this->actingAs($user)
        ->put(screeningReferralUrl($this->campaign->id, $this->screening->employee_id), screeningReferralForm())
        ->assertRedirect(route('tenant.campaigns.screenings', [$this->tenant, $this->campaign->id, 'person' => $this->screening->employee_id]))
        ->assertSessionHasNoErrors();

    expect(ScreeningReferral::query()->sole())
        ->tenant_id->toBe($this->tenant->id)
        ->campaign_id->toBe($this->campaign->id)
        ->employee_id->toBe($this->screening->employee_id)
        ->status->toBe(ReferralStatus::Referred)
        ->destination->toBe(Destination::Imss)
        ->told->toBe(HowTold::InPerson)
        ->referred_on->toBe('2026-11-18')
        ->note->toBe('Cita en la UMF 12 el lunes')
        ->remind_on->toBe('2026-12-18')
        ->reminded_at->toBeNull()
        ->institution->toBeNull()
        ->updated_by->toBe($user->id);
})->with([TenantRole::Owner, TenantRole::Hr]);

it('counts a person who did not want care as channeled, and keeps no reminder when none is asked for', function (): void {
    $this->actingAs($this->hr)
        ->put(screeningReferralUrl($this->campaign->id, $this->screening->employee_id), screeningReferralForm(['destination' => 'declined', 'told' => 'portal', 'note' => '', 'remind' => '0']))
        ->assertSessionHasNoErrors();

    expect(ScreeningReferral::query()->sole())
        ->status->toBe(ReferralStatus::Referred)
        ->destination->toBe(Destination::Declined)
        ->told->toBe(HowTold::Portal)
        ->note->toBeNull()
        ->remind_on->toBeNull();
});

it('moves the same record on to attended instead of adding another, and a new reminder starts over', function (): void {
    $url = screeningReferralUrl($this->campaign->id, $this->screening->employee_id);
    $this->actingAs($this->hr)->put($url, screeningReferralForm())->assertSessionHasNoErrors();
    ScreeningReferral::query()->update(['reminded_at' => now()]);

    $this->put($url, screeningReferralForm(['attended' => '1', 'destination' => 'external_psychological', 'referred_on' => '2026-11-19']))->assertSessionHasNoErrors();

    expect(ScreeningReferral::query()->sole())
        ->status->toBe(ReferralStatus::Attended)
        ->destination->toBe(Destination::ExternalPsychological)
        ->remind_on->toBe('2026-12-19')
        ->reminded_at->toBeNull();
});

it('trims the note and accepts today as the day', function (): void {
    $this->actingAs($this->hr)
        ->put(screeningReferralUrl($this->campaign->id, $this->screening->employee_id), screeningReferralForm(['note' => "  Cita el lunes \n", 'referred_on' => '2026-11-20']))
        ->assertSessionHasNoErrors();

    expect(ScreeningReferral::query()->sole())->note->toBe('Cita el lunes')->referred_on->toBe('2026-11-20');
});

it('records in the audit trail who changed the status, without the institution or the name', function (): void {
    $this->actingAs($this->hr)->put(screeningReferralUrl($this->campaign->id, $this->screening->employee_id), screeningReferralForm());

    $referral = ScreeningReferral::query()->sole();
    expect(AuditEntry::query()->where('event', 'results.referral_recorded')->sole())
        ->actor_id->toBe($this->hr->id)
        ->subject_id->toBe($referral->id)
        ->metadata->toEqual(['campaign_id' => $this->campaign->id, 'status' => 'referred']);
});

it('writes to the log only that a record was saved, never the person, the status, the destination or the note', function (): void {
    Log::spy();

    $this->actingAs($this->hr)->put(screeningReferralUrl($this->campaign->id, $this->screening->employee_id), screeningReferralForm());

    $referral = ScreeningReferral::query()->sole();
    Log::shouldHaveReceived('info')->with('screening_referral.saved', ['campaign_id' => $this->campaign->id, 'referral_id' => $referral->id])->once();
});

it('refuses what the record cannot hold', function (array $overrides, string $field): void {
    $this->actingAs($this->hr)
        ->from('/back')
        ->put(screeningReferralUrl($this->campaign->id, $this->screening->employee_id), screeningReferralForm($overrides))
        ->assertRedirect('/back')
        ->assertSessionHasErrors($field);

    expect(ScreeningReferral::query()->exists())->toBeFalse();
})->with([
    'no destination' => [['destination' => ''], 'destination'],
    'a destination it does not know' => [['destination' => 'hospital'], 'destination'],
    'not saying how they were told' => [['told' => ''], 'told'],
    'a way of telling it does not know' => [['told' => 'email'], 'told'],
    'no day' => [['referred_on' => ''], 'referred_on'],
    'a day written wrong' => [['referred_on' => '18/11/2026'], 'referred_on'],
    'a day that has not come' => [['referred_on' => '2026-11-21'], 'referred_on'],
    'a very long note' => [['note' => str_repeat('a', 201)], 'note'],
    'a reminder that is not yes or no' => [['remind' => 'tal vez'], 'remind'],
]);

it('names the missing destination in Spanish', function (): void {
    $this->actingAs($this->hr)
        ->put(screeningReferralUrl($this->campaign->id, $this->screening->employee_id), screeningReferralForm(['destination' => '']))
        ->assertSessionHasErrors(['destination' => 'El campo a dónde se canaliza es obligatorio.']);
});

it('accepts a note of exactly 200 characters', function (): void {
    $this->actingAs($this->hr)
        ->put(screeningReferralUrl($this->campaign->id, $this->screening->employee_id), screeningReferralForm(['note' => str_repeat('a', 200)]))
        ->assertSessionHasNoErrors();

    expect(ScreeningReferral::query()->count())->toBe(1);
});

it('hides the record from a viewer, with the same 404 as one that does not exist', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->put(screeningReferralUrl($this->campaign->id, $this->screening->employee_id), [])
        ->assertNotFound();

    expect(ScreeningReferral::query()->exists())->toBeFalse()
        ->and(AuditEntry::query()->where('event', 'results.referral_recorded')->exists())->toBeFalse();
});

it('hides the record from an impersonating superadmin', function (): void {
    ownerOf($this->tenant);
    $this->actingAs(superadmin())->post(route('companies.impersonate', $this->tenant))->assertRedirect();

    $this->put(screeningReferralUrl($this->campaign->id, $this->screening->employee_id), screeningReferralForm())->assertNotFound();

    expect(ScreeningReferral::query()->withoutGlobalScopes()->exists())->toBeFalse();
});

it('finds nobody to record for when they do not require a clinical assessment or belong elsewhere', function (Closure $target): void {
    [$campaignId, $employeeId] = $target($this);

    $this->actingAs($this->hr)->put(screeningReferralUrl($campaignId, $employeeId), screeningReferralForm())->assertNotFound();

    expect(ScreeningReferral::query()->withoutGlobalScopes()->exists())->toBeFalse();
})->with([
    'a person who does not require it' => fn ($test) => [$test->campaign->id, TraumaScreening::factory()->create(['campaign_id' => $test->campaign->id])->employee_id],
    'a person with no Guía I' => fn ($test) => [$test->campaign->id, Employee::factory()->create(['tenant_id' => $test->tenant->id, 'work_center_id' => $test->plant->id])->id],
    'a person of another company' => fn ($test) => [$test->campaign->id, TraumaScreening::factory()->create(['requires_clinical_assessment' => true])->employee_id],
    'a campaign of another company' => function ($test) {
        $other = TraumaScreening::factory()->create(['requires_clinical_assessment' => true]);

        return [$other->campaign_id, $other->employee_id];
    },
    'a campaign that does not exist' => fn ($test) => ['9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c', $test->screening->employee_id],
]);

it('shows each record as the page reads it, and counts the people by status', function (): void {
    $attended = TraumaScreening::factory()->create(['campaign_id' => $this->campaign->id, 'requires_clinical_assessment' => true, 'answered_at' => '2026-11-10 15:00:00']);
    TraumaScreening::factory()->create(['campaign_id' => $this->campaign->id, 'requires_clinical_assessment' => true, 'answered_at' => '2026-11-21 15:00:00']);
    $this->actingAs($this->hr);
    $this->put(screeningReferralUrl($this->campaign->id, $attended->employee_id), screeningReferralForm(['attended' => '1', 'referred_on' => '2026-11-03']));
    $this->put(screeningReferralUrl($this->campaign->id, $this->screening->employee_id), screeningReferralForm(['remind' => '0', 'told' => 'portal', 'destination' => 'declined']));

    $this->get(route('tenant.campaigns.screenings', [$this->tenant, $this->campaign->id, 'view' => 'channeled']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('referralCounts', ['pending' => 1, 'referred' => 1, 'attended' => 1])
            ->where('people.data.0.referral', [
                'status' => 'attended', 'statusLabel' => 'Atendida', 'destination' => 'imss', 'destinationLabel' => 'IMSS', 'told' => 'in_person', 'toldLabel' => 'En persona, en privado',
                'referredOn' => '2026-11-03', 'referredOnLabel' => '3 nov 2026', 'note' => 'Cita en la UMF 12 el lunes', 'remindOn' => '3 dic 2026', 'institution' => null,
            ])
            ->where('people.data.1.referral.destinationLabel', 'No quiso atenderse')
            ->where('people.data.1.referral.toldLabel', 'Aviso en su portal')
            ->where('people.data.1.referral.remindOn', null));
});

it('keeps company A from reading or changing the records of company B', function (): void {
    $mine = ScreeningReferral::factory()->create();
    $theirs = ScreeningReferral::factory()->create(['institution' => 'Ajena']);
    workIn(Tenant::query()->findOrFail($mine->tenant_id));

    expect(ScreeningReferral::query()->pluck('id')->all())->toBe([$mine->id])
        ->and(ScreeningReferral::query()->whereKey($theirs->id)->update(['institution' => 'Robada']))->toBe(0)
        ->and(ScreeningReferral::query()->withoutGlobalScopes()->findOrFail($theirs->id)->institution)->toBe('Ajena');
});

it('lets the database refuse a record with no Guía I behind it, an unknown status or a second record for one person', function (): void {
    $attributes = ['campaign_id' => $this->campaign->id, 'tenant_id' => $this->tenant->id, 'employee_id' => $this->screening->employee_id];
    ScreeningReferral::factory()->create($attributes);
    $withoutGuide = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id])->id;

    expect(fn () => ScreeningReferral::factory()->create($attributes))->toThrow(QueryException::class);
    expect(fn () => ScreeningReferral::factory()->create([...$attributes, 'employee_id' => $withoutGuide]))->toThrow(QueryException::class);
    expect(fn () => ScreeningReferral::query()->withoutGlobalScopes()->where('campaign_id', $this->campaign->id)->toBase()->update(['status' => 'pending']))->toThrow(QueryException::class);
    expect(fn () => ScreeningReferral::query()->withoutGlobalScopes()->where('campaign_id', $this->campaign->id)->toBase()->update(['destination' => 'hospital']))->toThrow(QueryException::class);
    expect(fn () => ScreeningReferral::query()->withoutGlobalScopes()->where('campaign_id', $this->campaign->id)->toBase()->update(['told' => 'email']))->toThrow(QueryException::class);
});
