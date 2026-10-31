<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Results\Actions\CreateReportShare;
use App\Modules\Results\Actions\RevokeReportShare;
use App\Modules\Results\Enums\ShareDuration;
use App\Modules\Results\Http\Controllers\ReportShareController;
use App\Modules\Results\Http\Controllers\SharedReportController;
use App\Modules\Results\Models\ReportShare;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ReportShareController::class, SharedReportController::class, CreateReportShare::class, RevokeReportShare::class, ShareDuration::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->evaluation = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
});

/** Creates a link to the evaluation's report as HR and returns its token, taken from the link it flashes once. */
function shareReport(Campaign $campaign, Tenant $tenant, string $duration = '24h'): string
{
    $response = test()->post(route('tenant.campaigns.shares.store', [$tenant, $campaign->id]), ['duration' => $duration]);
    $link = session(SessionKey::FLASH_DATA)['reportLink']['url'] ?? null;
    $response->assertRedirect(route('tenant.campaigns.report', [$tenant, $campaign->id]));

    return is_string($link) ? basename($link) : throw new RuntimeException('No link was flashed.');
}

it('creates a link that lasts what HR chose, and keeps only its hash', function (string $duration, int $minutes): void {
    Log::spy();
    $this->actingAs($this->hr);

    $token = shareReport($this->evaluation, $this->tenant, $duration);
    $share = ReportShare::query()->withoutGlobalScopes()->sole();

    expect(strlen($token))->toBe(64)
        ->and($share->token_hash)->toBe(hash('sha256', $token))
        ->and($share->expires_at->equalTo(now()->addMinutes($minutes)))->toBeTrue()
        ->and($share->campaign_id)->toBe($this->evaluation->id)
        ->and($share->created_by)->toBe($this->hr->id)
        ->and(session(SessionKey::FLASH_DATA)['reportLink'])->toBe(['url' => route('report.shared', $token), 'expiresAt' => str_replace('.', '', $share->expires_at->setTimezone('America/Mexico_City')->isoFormat('D MMM YYYY, HH:mm'))])
        ->and(json_encode(DB::table('result_shares')->get(), JSON_THROW_ON_ERROR))->not->toContain($token);
    expect(AuditEntry::query()->where('event', 'results.share_created')->sole())
        ->actor_id->toBe($this->hr->id)
        ->metadata->toEqual(['campaign_id' => $this->evaluation->id, 'share_id' => $share->id, 'minutes' => $minutes]);
    Log::shouldHaveReceived('info')->with('result_share.created', ['campaign_id' => $this->evaluation->id, 'share_id' => $share->id, 'minutes' => $minutes])->once();
})->with([['15m', 15], ['30m', 30], ['1h', 60], ['24h', 1440], ['36h', 2160], ['48h', 2880]]);

it('offers the durations from 15 minutes to 48 hours, by name', function (): void {
    expect(ShareDuration::catalog())->toBe([
        ['value' => '15m', 'label' => '15 minutos'],
        ['value' => '30m', 'label' => '30 minutos'],
        ['value' => '1h', 'label' => '1 hora'],
        ['value' => '24h', 'label' => '24 horas'],
        ['value' => '36h', 'label' => '36 horas'],
        ['value' => '48h', 'label' => '48 horas'],
    ]);
});

it('accepts no other duration', function (mixed $duration): void {
    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.shares.store', [$this->tenant, $this->evaluation->id]), ['duration' => $duration])
        ->assertSessionHasErrors(['duration' => 'Elige cuánto dura el enlace: de 15 minutos a 48 horas.']);

    expect(ReportShare::query()->withoutGlobalScopes()->exists())->toBeFalse();
})->with(['72h', '0', '', 'forever']);

it('refuses in the database a link of more than 48 hours', function (): void {
    $share = ReportShare::factory()->create(['campaign_id' => $this->evaluation->id]);

    DB::table('result_shares')->where('id', $share->id)->update(['expires_at' => $share->created_at->addHours(48)->addSecond()]);
})->throws(QueryException::class);

it('lets only those who manage campaigns create or revoke a link', function (): void {
    $viewer = memberOf($this->tenant, TenantRole::Viewer);
    $share = ReportShare::factory()->create(['campaign_id' => $this->evaluation->id]);

    $this->actingAs($viewer)->post(route('tenant.campaigns.shares.store', [$this->tenant, $this->evaluation->id]), ['duration' => '1h'])->assertForbidden();
    $this->actingAs($viewer)->delete(route('tenant.campaigns.shares.destroy', [$this->tenant, $this->evaluation->id, $share->id]))->assertForbidden();

    expect(ReportShare::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and($share->fresh()?->revoked_at)->toBeNull();
});

it('shares no report of an open evaluation or of another company', function (Closure $campaign): void {
    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.shares.store', [$this->tenant, $campaign($this->tenant)->id]), ['duration' => '1h'])
        ->assertNotFound();
})->with([
    'open' => fn (Tenant $tenant) => Campaign::factory()->active()->create(['tenant_id' => $tenant->id]),
    'another company' => fn (Tenant $tenant) => Campaign::factory()->closed()->create(),
]);

it('opens the report from the link, with no account, and records each opening', function (): void {
    AnonymousResponse::factory()->count(5)->create(['campaign_id' => $this->evaluation->id, 'answers' => nom035Answers('nom035.guide-ii', 'never')]);
    TraumaScreening::factory()->create(['campaign_id' => $this->evaluation->id, 'requires_clinical_assessment' => true]);
    $token = str_repeat('a', 64);
    $share = ReportShare::factory()->create(['campaign_id' => $this->evaluation->id, 'token_hash' => hash('sha256', $token), 'created_at' => now(), 'expires_at' => now()->addHour()]);

    $response = $this->get(route('report.shared', $token))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $page) => $page
            ->component('results/shared-report')
            ->where('company', 'Acme')
            ->where('expiresAt', '20 nov 2026, 13:00')
            ->where('report.center.name', 'Planta Norte')
            ->where('report.results.final', [0, 0, 5, 0, 0])
            ->has('levels', 5));

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $props = json_encode($response->viewData('page')['props'], JSON_THROW_ON_ERROR);
    expectNoIndividualResponses($response->viewData('page')['props']);
    expect($props)->not->toContain('trauma')->not->toContain('requiring')->not->toContain('"answers"')->not->toContain('"items"');
    expect(AuditEntry::query()->where('event', 'results.share_opened')->sole())
        ->tenant_id->toBe($this->tenant->id)
        ->actor_id->toBeNull()
        ->metadata->toEqual(['campaign_id' => $this->evaluation->id, 'share_id' => $share->id]);
});

it('stops opening when the link expires, is revoked, is unknown or its company is blocked', function (Closure $break): void {
    $token = str_repeat('a', 64);
    ReportShare::factory()->create(['campaign_id' => $this->evaluation->id, 'token_hash' => hash('sha256', $token), 'created_at' => now(), 'expires_at' => now()->addMinutes(15)]);
    $break($token, $this->tenant);

    $this->get(route('report.shared', $token))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('results/shared-report')->where('report', null)->where('company', null));

    expect(AuditEntry::query()->where('event', 'results.share_opened')->exists())->toBeFalse();
})->with([
    'expired, to the second' => function (string &$token, Tenant $tenant): void {
        Carbon::setTestNow(now()->addMinutes(15));
    },
    'revoked' => function (string &$token, Tenant $tenant): void {
        DB::table('result_shares')->update(['revoked_at' => now()]);
    },
    'unknown' => function (string &$token, Tenant $tenant): void {
        $token = str_repeat('b', 64);
    },
    'blocked company' => function (string &$token, Tenant $tenant): void {
        $tenant->update(['deactivated_at' => now()]);
    },
]);

it('opens a link until its last second', function (): void {
    $token = str_repeat('a', 64);
    ReportShare::factory()->create(['campaign_id' => $this->evaluation->id, 'token_hash' => hash('sha256', $token), 'created_at' => now(), 'expires_at' => now()->addMinutes(15)]);
    Carbon::setTestNow(now()->addMinutes(15)->subSecond());

    $this->get(route('report.shared', $token))->assertOk();
});

it('revokes a link of the evaluation, and records who did', function (): void {
    Log::spy();
    $share = ReportShare::factory()->create(['campaign_id' => $this->evaluation->id]);

    $this->actingAs($this->hr)
        ->delete(route('tenant.campaigns.shares.destroy', [$this->tenant, $this->evaluation->id, $share->id]))
        ->assertRedirect(route('tenant.campaigns.report', [$this->tenant, $this->evaluation->id]))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Enlace revocado.']]);

    expect($share->fresh()?->revoked_at?->equalTo(now()))->toBeTrue()
        ->and(AuditEntry::query()->where('event', 'results.share_revoked')->sole()->metadata)->toEqual(['campaign_id' => $this->evaluation->id, 'share_id' => $share->id]);
    Log::shouldHaveReceived('info')->with('result_share.revoked', ['campaign_id' => $this->evaluation->id, 'share_id' => $share->id])->once();
});

it('revokes no link of another evaluation or company', function (): void {
    $otherEvaluation = ReportShare::factory()->create(['campaign_id' => Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id])->id]);
    $otherCompany = ReportShare::factory()->create();

    foreach ([$otherEvaluation, $otherCompany] as $share) {
        $this->actingAs($this->hr)
            ->delete(route('tenant.campaigns.shares.destroy', [$this->tenant, $this->evaluation->id, $share->id]))
            ->assertNotFound();
    }

    expect(ReportShare::query()->withoutGlobalScopes()->whereNotNull('revoked_at')->exists())->toBeFalse();
});

it('lists the links still open on the report, to those who manage campaigns', function (): void {
    $open = ReportShare::factory()->create(['campaign_id' => $this->evaluation->id, 'created_at' => now()->subHour(), 'expires_at' => now()->addHours(2)]);
    ReportShare::factory()->create(['campaign_id' => $this->evaluation->id, 'created_at' => now()->subHours(2), 'expires_at' => now()->subMinute()]);
    ReportShare::factory()->create(['campaign_id' => $this->evaluation->id, 'revoked_at' => now()]);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.report', [$this->tenant, $this->evaluation->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('durations.0', ['value' => '15m', 'label' => '15 minutos'])
            ->where('shares.total', 1)
            ->where('shares.data.0', ['id' => $open->id, 'createdAt' => '20 nov 2026, 11:00', 'expiresAt' => '20 nov 2026, 14:00']));

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->get(route('tenant.campaigns.report', [$this->tenant, $this->evaluation->id]))
        ->assertInertia(fn (Assert $page) => $page->where('shares', null)->where('durations', null));
});
