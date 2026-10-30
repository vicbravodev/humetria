<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Tenancy\Models\Tenant;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/**
 * A person invited to Guía I and the guide given, whose link is /survey/the-token.
 *
 * @param  list<string>  $instruments
 */
function invitedPerson(array $instruments = ['nom035.guide-i', 'nom035.guide-iii']): Participant
{
    $tenant = Tenant::factory()->create(['trade_name' => 'Autopartes del Bajío']);
    $campaign = Campaign::factory()->active(now()->addDays(10)->toDateString())->create([
        'tenant_id' => $tenant->id,
        'instruments' => $instruments,
    ]);
    $participant = Participant::factory()->create(['campaign_id' => $campaign->id, 'token_hash' => Participant::hashToken('the-token')]);

    foreach ($instruments as $instrument) {
        ParticipantInstrument::factory()->create(['participant_id' => $participant->id, 'instrument' => $instrument]);
    }

    return $participant;
}

it('opens the survey with what the norm asks to say before it', function (bool $dark, bool $mobile): void {
    invitedPerson();

    visitPage('campaigns/survey', '/survey/the-token', $dark, $mobile)
        ->assertSee('Autopartes del Bajío')
        ->assertSee('Confidencial')
        ->assertSee('dos últimos meses')
        ->assertSee('No hay respuestas correctas ni incorrectas.');
})->with($looks);

it('explains a link that does not work, naming no company', function (bool $dark, bool $mobile): void {
    invitedPerson();

    visitPage('campaigns/survey', '/survey/not-the-token', $dark, $mobile)
        ->assertSee('Este enlace no es válido')
        ->assertDontSee('Autopartes del Bajío');
})->with($looks);
