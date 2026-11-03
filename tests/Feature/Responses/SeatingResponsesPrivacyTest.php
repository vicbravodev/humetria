<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-02 17:00:00');
    Bus::fake();
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->people = staffOf($this->tenant, $this->plant, 3)->values();
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))->post(route('tenant.seating.survey.launch', [$this->tenant, $this->plant->id]), ['closes_on' => '2026-11-12'])->assertSessionHasNoErrors();
    $this->participant = Participant::query()->where('employee_id', $this->people[0]->id)->sole();
    $this->participant->forceFill(['token_hash' => Participant::hashToken('the-token')])->save();
});

it('stores the answer under the center and the current area of the person, not under the person', function (): void {
    $this->post(route('survey.submit', ['the-token', 'seating.survey']), ['answers' => surveyAnswers('no')])->assertSessionHasNoErrors();

    $response = AnonymousResponse::query()->sole();
    expect($response->work_center_id)->toBe($this->plant->id)->and($response->area_id)->toBe($this->people[0]->area_id)->and($response->instrument)->toBe('seating.survey')
        ->and($response->campaign_id)->toBe(Campaign::query()->sole()->id)->and($response->answers['stands'])->toBe('yes');
});

it('keeps no column, key or index toward the person, the participant, the invitation or the token, and no time', function (): void {
    $columns = collect(DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'anonymous_responses' ORDER BY column_name"))->pluck('column_name')->all();
    $references = collect(DB::select(<<<'SQL'
        SELECT DISTINCT target.relname AS referenced FROM pg_constraint c
        JOIN pg_class source ON source.oid = c.conrelid JOIN pg_class target ON target.oid = c.confrelid
        WHERE source.relname = 'anonymous_responses' AND c.contype = 'f' ORDER BY referenced
    SQL))->pluck('referenced')->all();

    expect($columns)->toBe(['answers', 'area_id', 'campaign_id', 'id', 'instrument', 'instrument_version', 'tenant_id', 'work_center_id'])
        ->and($references)->toBe(['areas', 'campaigns', 'tenants', 'work_centers']);
});

it('gives the response a random UUID v4, never one that orders by time', function (): void {
    $this->post(route('survey.submit', ['the-token', 'seating.survey']), ['answers' => surveyAnswers()]);

    $response = AnonymousResponse::query()->sole();
    expect($response->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('writes no answer value to the log', function (): void {
    Log::spy();

    $this->post(route('survey.submit', ['the-token', 'seating.survey']), ['answers' => surveyAnswers('not_applicable')]);

    Log::shouldNotHaveReceived('info', fn ($message, $context = []): bool => str_contains(json_encode($context), 'not_applicable') || array_key_exists('answers', (array) $context));
    Log::shouldHaveReceived('info')->with('campaign_participant.completed', ['participant_id' => $this->participant->id, 'instrument' => 'seating.survey'])->once();
});
