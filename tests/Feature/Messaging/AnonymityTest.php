<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Messaging\DeliveryFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The tables where an anonymous answer lives: nothing in them may name a channel, a message, a link or a phone. */
const ANSWER_TABLES = ['anonymous_responses', 'engagement_responses', 'engagement_comments', 'engagement_comment_summaries'];

it('has no column in the answers that looks like a channel, a message, a token or a phone', function (string $table): void {
    expect(Schema::hasTable($table))->toBeTrue();

    $suspicious = array_values(array_filter(Schema::getColumnListing($table), fn (string $column): bool => preg_match('/channel|invited_via|wamid|provider_message|token|phone|mobile|recipient|delivery/', $column) === 1));

    expect($suspicious)->toBe([]);
})->with(ANSWER_TABLES);

it('has no foreign key between the answers and the messaging tables, in either direction', function (): void {
    $links = DB::select(<<<'SQL'
        select tc.table_name as source, ccu.table_name as target
        from information_schema.table_constraints tc
        join information_schema.constraint_column_usage ccu on ccu.constraint_name = tc.constraint_name and ccu.constraint_schema = tc.constraint_schema
        where tc.constraint_type = 'FOREIGN KEY'
    SQL);

    $crossing = array_values(array_filter($links, fn (object $link): bool => (str_starts_with($link->source, 'messaging_') && in_array($link->target, ANSWER_TABLES, true))
        || (in_array($link->source, ANSWER_TABLES, true) && str_starts_with($link->target, 'messaging_'))));

    expect($crossing)->toBe([]);
});

it('keeps the channel only in the invitations', function (): void {
    expect(Schema::hasColumn('campaign_participants', 'invited_via'))->toBeTrue()
        ->and(Schema::hasColumn('engagement_invitations', 'invited_via'))->toBeTrue();
});

it('puts no per-person delivery data in the props of the pages RH sees', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $hr = memberOf($tenant, TenantRole::Hr);
    $campaign = Campaign::factory()->active('2026-11-30')->create(['tenant_id' => $tenant->id, 'work_center_id' => WorkCenter::factory()->for($tenant)->create()->id]);
    $survey = Survey::factory()->active()->create(['tenant_id' => $tenant->id, 'invited' => 40]);
    foreach (range(1, 6) as $_) {
        DeliveryFactory::new()->create(['tenant_id' => $tenant->id, 'source_type' => 'campaign', 'source_id' => $campaign->id, 'status' => 'failed']);
        DeliveryFactory::new()->create(['tenant_id' => $tenant->id, 'source_type' => 'engagement_survey', 'source_id' => $survey->id, 'invitation_type' => 'engagement_invitation', 'status' => 'failed']);
    }

    foreach ([route('tenant.campaigns.show', [$tenant, $campaign->id]), route('tenant.climate.show', [$tenant, $survey])] as $url) {
        $json = json_encode(test()->actingAs($hr)->get($url)->assertOk()->inertiaProps(), JSON_THROW_ON_ERROR);

        expect($json)->not->toContain('invited_via')->not->toContain('recipient_hash')->not->toContain('provider_message_id')->not->toContain('occurred_at');
    }
});

it('gives the results pages the same props whatever channel is asked for', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $hr = memberOf($tenant, TenantRole::Hr);
    $survey = Survey::factory()->closed()->create(['tenant_id' => $tenant->id]);
    $url = route('tenant.climate.results', [$tenant, $survey]);

    $plain = test()->actingAs($hr)->get($url)->assertOk()->inertiaProps();
    $filtered = test()->actingAs($hr)->get($url.'?channel=whatsapp')->assertOk()->inertiaProps();

    expect($filtered)->toEqual($plain);
});
