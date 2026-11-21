<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Participation;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Messaging\DeliveryFactory;
use Database\Factories\Tenancy\TenantChannelFactory;

mutates(Participation::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->survey = Survey::factory()->active()->create(['tenant_id' => $this->tenant->id, 'invited' => 40]);
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create();
});

function surveyMessages(Tenant $tenant, Survey $survey, string $channel, int $count, int $undelivered = 0): void
{
    foreach (range(1, $count) as $index) {
        $delivery = DeliveryFactory::new()->state(['tenant_id' => $tenant->id, 'source_type' => 'engagement_survey', 'source_id' => $survey->id, 'invitation_type' => 'engagement_invitation', 'channel' => $channel, 'status' => $index <= $undelivered ? DeliveryStatus::Failed : DeliveryStatus::Read]);
        ($channel === 'mail' ? $delivery->mail() : $delivery)->create();
    }
}

/** @return array<string, mixed> */
function surveyReach(Tests\TestCase $test): array
{
    $props = $test->actingAs($test->hr)->get(route('tenant.climate.show', [$test->tenant, $test->survey]))->assertOk()->inertiaProps();
    $reach = $props['participation']['reach'] ?? null;

    return is_array($reach) ? $reach : [];
}

function surveyPeople(Survey $survey, string $channel, int $answered, int $unanswered): void
{
    Invitation::factory()->count($answered)->create(['survey_id' => $survey->id, 'tenant_id' => $survey->tenant_id, 'invited_via' => $channel, 'answered' => true]);
    Invitation::factory()->count($unanswered)->create(['survey_id' => $survey->id, 'tenant_id' => $survey->tenant_id, 'invited_via' => $channel, 'answered' => false]);
}

it('shows one gauge per active channel with the total and how many did not arrive (CUJ 6)', function (): void {
    surveyMessages($this->tenant, $this->survey, 'mail', 20, 6);
    surveyMessages($this->tenant, $this->survey, 'whatsapp', 10, 5);

    expect(surveyReach($this)['channels'])->toBe([
        ['channel' => 'mail', 'total' => 20, 'delivered' => 14, 'undelivered' => 6, 'undeliveredBelowMinimum' => false],
        ['channel' => 'whatsapp', 'total' => 10, 'delivered' => 5, 'undelivered' => 5, 'undeliveredBelowMinimum' => false],
    ]);
});

it('applies the minimum of 5 to the total and to the undelivered: 4, 5 and 6', function (int $total, int $undelivered, array $expected): void {
    surveyMessages($this->tenant, $this->survey, 'whatsapp', $total, $undelivered);

    expect(surveyReach($this)['channels'])->toBe([['channel' => 'whatsapp', ...$expected]]);
})->with([
    [4, 4, ['total' => null, 'delivered' => null, 'undelivered' => null, 'undeliveredBelowMinimum' => false]],
    [5, 4, ['total' => 5, 'delivered' => null, 'undelivered' => null, 'undeliveredBelowMinimum' => true]],
    [6, 5, ['total' => 6, 'delivered' => 1, 'undelivered' => 5, 'undeliveredBelowMinimum' => false]],
]);

it('does not show a channel the company turned off, nor the messages of another company or survey', function (): void {
    surveyMessages($this->tenant, $this->survey, 'whatsapp', 8);
    $other = Tenant::factory()->create();
    surveyMessages($other, Survey::factory()->active()->create(['tenant_id' => $other->id]), 'mail', 9);
    surveyMessages($this->tenant, Survey::factory()->closed()->create(['tenant_id' => $this->tenant->id]), 'mail', 9);
    App\Modules\Tenancy\Models\TenantChannel::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update(['enabled' => false]);

    expect(surveyReach($this)['channels'])->toBe([]);
});

it('answers 404 for the survey of another company', function (): void {
    $other = Tenant::factory()->create();
    $foreign = Survey::factory()->active()->create(['tenant_id' => $other->id]);

    $this->actingAs($this->hr)->get(route('tenant.climate.show', [$this->tenant, $foreign]))->assertNotFound();
});

it('crosses the channel with who answered only when every cell reaches 5: 4, 5 and 6', function (int $cell, bool $shown): void {
    surveyPeople($this->survey, 'mail', 20, 20);
    surveyPeople($this->survey, 'whatsapp', $cell, 20);

    $answers = surveyReach($this)['answeredByChannel'];

    expect($answers === null)->toBe(! $shown);
    $shown && expect($answers)->toBe([
        ['channel' => 'mail', 'answered' => 20, 'unanswered' => 20],
        ['channel' => 'whatsapp', 'answered' => $cell, 'unanswered' => 20],
    ]);
})->with([[4, false], [5, true], [6, true]]);
