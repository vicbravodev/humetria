<?php

declare(strict_types=1);

use App\Modules\Campaigns\Actions\OpenSurvey;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Http\Controllers\SurveyController;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(OpenSurvey::class, SurveyController::class);

/**
 * A participant of an active campaign of Acme whose link is `$token`, given the guides listed (all pending).
 *
 * @param  list<string>  $instruments
 */
function surveyParticipant(string $token = 'the-token', array $instruments = ['nom035.guide-i', 'nom035.guide-ii'], array $campaign = []): Participant
{
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $participant = Participant::factory()->create([
        'campaign_id' => Campaign::factory()->active('2026-11-14')->create(['tenant_id' => $tenant->id, ...$campaign])->id,
        'token_hash' => Participant::hashToken($token),
    ]);

    foreach ($instruments as $instrument) {
        ParticipantInstrument::factory()->create(['participant_id' => $participant->id, 'instrument' => $instrument]);
    }

    return $participant;
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-01 15:00:00');
});

it('lives at /survey/{token}, outside the panel', function (): void {
    expect(route('survey.show', 'abc', absolute: false))->toBe('/survey/abc');
});

it('opens with the company, the deadline and each guide as the person answers it', function (): void {
    surveyParticipant();

    $this->get(route('survey.show', 'the-token'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/survey')
            ->where('token', 'the-token')
            ->where('unavailable', null)
            ->where('company', 'Acme')
            ->where('closesOn', '14 nov 2026')
            ->has('instruments', 2)
            ->where('instruments.0.key', 'nom035.guide-i')
            ->where('instruments.0.title', 'Acontecimientos traumáticos severos')
            ->where('instruments.0.shortTitle', 'Guía I')
            ->where('instruments.0.identified', true)
            ->where('instruments.0.status', 'pending')
            ->where('instruments.0.options', [['value' => 'yes', 'label' => 'Sí'], ['value' => 'no', 'label' => 'No']])
            ->where('instruments.0.sections.0.title', 'I.- Acontecimiento traumático severo')
            ->where('instruments.0.sections.0.items.0', ['number' => 1, 'text' => 'Accidente que tenga como consecuencia la muerte, la pérdida de un miembro o una lesión grave?'])
            ->where('instruments.1.identified', false)
            ->where('instruments.1.sections.6.gate', [
                'key' => 'serves_clients',
                'question' => 'En mi trabajo debo brindar servicio a clientes o usuarios:',
                'instruction' => 'Si su respuesta fue "SÍ", responda las preguntas siguientes. Si su respuesta fue "NO" pase a las preguntas de la sección siguiente.',
            ])
            ->has('instruments.1.sections.0.items', 9));
});

it('shows an answered guide as done', function (): void {
    $participant = surveyParticipant(instruments: ['nom035.guide-ii']);
    ParticipantInstrument::factory()->completed()->create(['participant_id' => $participant->id, 'instrument' => 'nom035.guide-i']);

    $this->get(route('survey.show', 'the-token'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('instruments', 2)
            ->where('instruments.0.key', 'nom035.guide-i')
            ->where('instruments.0.status', 'completed')
            ->where('instruments.1.key', 'nom035.guide-ii')
            ->where('instruments.1.status', 'pending'));
});

it('leaves out a cancelled guide', function (): void {
    surveyParticipant();
    ParticipantInstrument::query()->withoutGlobalScopes()->where('instrument', 'nom035.guide-ii')->update(['status' => InstrumentStatus::Cancelled, 'cancelled_at' => now()]);

    $this->get(route('survey.show', 'the-token'))
        ->assertInertia(fn (Assert $page) => $page->has('instruments', 1)->where('instruments.0.key', 'nom035.guide-i'));
});

it('says a link is not valid without naming any company', function (string $token): void {
    surveyParticipant();

    $this->get(route('survey.show', $token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/survey')
            ->where('unavailable', 'invalid')
            ->where('token', null)
            ->where('company', null)
            ->where('instruments', []));
})->with(['another-token', str_repeat('a', 64)]);

it('says the evaluation closed once closed or past its last day, in Mexico City', function (array $campaign, string $now, ?string $reason): void {
    surveyParticipant(campaign: $campaign);
    Carbon::setTestNow($now);

    $this->get(route('survey.show', 'the-token'))
        ->assertInertia(fn (Assert $page) => $page->where('unavailable', $reason)->where('company', 'Acme'));
})->with([
    'closed' => [['status' => 'closed', 'closed_at' => '2026-11-01 12:00:00'], '2026-11-01 15:00:00', 'closed'],
    'the last day, 23:59 in Mexico City' => [[], '2026-11-15 05:59:00', null],
    'the day after, 00:00 in Mexico City' => [[], '2026-11-15 06:00:00', 'closed'],
]);

it('says there is nothing left once every guide is answered', function (): void {
    surveyParticipant();
    ParticipantInstrument::query()->withoutGlobalScopes()->update(['status' => InstrumentStatus::Completed, 'completed_at' => now()]);

    $this->get(route('survey.show', 'the-token'))->assertInertia(fn (Assert $page) => $page->where('unavailable', 'done'));
});

it('says the evaluation is not available while the company is suspended', function (): void {
    $participant = surveyParticipant();
    Tenant::query()->whereKey($participant->tenant_id)->update(['deactivated_at' => now()]);

    $this->get(route('survey.show', 'the-token'))->assertInertia(fn (Assert $page) => $page->where('unavailable', 'suspended'));
});

it('needs no account, and a signed-in person sees the same survey', function (): void {
    surveyParticipant();

    $this->get(route('survey.show', 'the-token'))->assertInertia(fn (Assert $page) => $page->where('unavailable', null));
    $this->actingAs(superadmin())->get(route('survey.show', 'the-token'))->assertInertia(fn (Assert $page) => $page->where('unavailable', null));
});

it('limits how often one link is opened, without blocking a room of people on one network', function (): void {
    surveyParticipant();

    foreach (range(1, 30) as $attempt) {
        $this->get(route('survey.show', 'the-token'))->assertOk();
    }

    $this->get(route('survey.show', 'the-token'))->assertTooManyRequests();

    // A group session: a hundred people on the same Wi-Fi, each with their own link.
    foreach (range(1, 100) as $person) {
        $this->get(route('survey.show', "link-{$person}"))->assertOk();
    }
});

it('limits how many links one network can try', function (): void {
    foreach (range(1, 600) as $attempt) {
        $this->get(route('survey.show', "guess-{$attempt}"));
    }

    $this->get(route('survey.show', 'guess-601'))->assertTooManyRequests();
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->get(route('survey.show', 'guess-602'))->assertOk();
});
