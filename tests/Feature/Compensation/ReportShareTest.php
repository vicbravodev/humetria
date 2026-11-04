<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Compensation\Actions\CreateReportShare;
use App\Modules\Compensation\Actions\RevokeReportShare;
use App\Modules\Compensation\Models\ReportShare;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

mutates(CreateReportShare::class, RevokeReportShare::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-04 10:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->actingAs($this->owner);
    workIn($this->tenant);
    $this->scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id]);
});

function shareUrl(Tenant $tenant, Scenario $scenario): string
{
    return route('tenant.compensation.scenarios.shares.store', [$tenant, $scenario->id]);
}

it('opens a link for the days asked and flashes it once, in the clear, while keeping only its hash', function (): void {
    Log::spy();

    $this->post(shareUrl($this->tenant, $this->scenario), ['days' => '3'])->assertRedirect()->assertSessionHasNoErrors();

    $share = ReportShare::query()->sole();

    expect($share->scenario_id)->toBe($this->scenario->id)
        ->and($share->tenant_id)->toBe($this->tenant->id)
        ->and($share->created_by)->toBe($this->owner->id)
        ->and($share->expires_at->toDateTimeString())->toBe('2026-11-07 10:00:00')
        ->and($share->token_hash)->toHaveLength(64)
        ->and($share->revoked_at)->toBeNull();
    $flashed = session(SessionKey::FLASH_DATA)['reportLink'];
    $token = Str::afterLast($flashed['url'], '/');

    expect($flashed['url'])->toStartWith(url('/compensation-report/'))->and(ReportShare::hashToken($token))->toBe($share->token_hash)
        ->and($flashed['expiresAt'])->toBe('7 nov 2026, 04:00');
    Log::shouldHaveReceived('info')->with('compensation.share_created', ['scenario_id' => $this->scenario->id, 'share_id' => $share->id, 'days' => 3])->once();
    expect(AuditEntry::query()->where('event', 'compensation.share_created')->sole()->metadata)->toEqual(['scenario_id' => $this->scenario->id, 'share_id' => $share->id, 'days' => 3]);
});

it('gives the link back as a one-time flash whose token hashes to the stored one', function (): void {
    [$share, $token] = resolve(CreateReportShare::class)->handle($this->scenario, 1, $this->owner->id);

    expect($token)->toHaveLength(64)
        ->and(ReportShare::hashToken($token))->toBe($share->token_hash)
        ->and($share->expires_at->toDateTimeString())->toBe('2026-11-05 10:00:00');
});

it('keeps the token out of the log and the audit trail', function (): void {
    Log::spy();

    [, $token] = resolve(CreateReportShare::class)->handle($this->scenario, 1, $this->owner->id);

    expect(json_encode(AuditEntry::query()->get()->toArray()))->not->toContain($token);
    Log::shouldNotHaveReceived('info', fn (string $message, array $context = []): bool => str_contains(json_encode($context) ?: '', $token));
});

it('lasts 7 days at most and at least 1', function (): void {
    foreach (['0', '8', '-1', 'x', ''] as $days) {
        $this->post(shareUrl($this->tenant, $this->scenario), ['days' => $days])->assertSessionHasErrors('days');
    }

    $this->post(shareUrl($this->tenant, $this->scenario), ['days' => '7'])->assertSessionHasNoErrors();

    expect(ReportShare::query()->sole()->expires_at->toDateTimeString())->toBe('2026-11-11 10:00:00')
        ->and(fn () => resolve(CreateReportShare::class)->handle($this->scenario, 8, $this->owner->id))->toThrow(Illuminate\Validation\ValidationException::class);
});

it('has the database refuse a link of more than 7 days or that ends before it starts', function (): void {
    $insert = fn (string $expires): bool => DB::table('compensation_report_shares')->insert([
        'id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->id, 'scenario_id' => $this->scenario->id, 'token_hash' => hash('sha256', Str::random(8)),
        'created_by' => $this->owner->id, 'created_at' => now(), 'updated_at' => now(), 'expires_at' => $expires,
    ]);

    expect(fn () => DB::transaction(fn () => $insert(now()->addDays(7)->addSecond()->toDateTimeString())))->toThrow(QueryException::class, 'compensation_report_shares_at_most_7_days')
        ->and(fn () => DB::transaction(fn () => $insert(now()->toDateTimeString())))->toThrow(QueryException::class, 'compensation_report_shares_at_most_7_days')
        ->and($insert(now()->addDays(7)->toDateTimeString()))->toBeTrue();
});

it('lists the links that still open on the report, the newest first, five at a time', function (): void {
    ReportShare::factory()->count(6)->create(['scenario_id' => $this->scenario->id, 'tenant_id' => $this->tenant->id, 'created_by' => $this->owner->id]);
    ReportShare::factory()->create(['scenario_id' => $this->scenario->id, 'tenant_id' => $this->tenant->id, 'created_by' => $this->owner->id, 'revoked_at' => now()]);
    ReportShare::factory()->create(['scenario_id' => $this->scenario->id, 'tenant_id' => $this->tenant->id, 'created_by' => $this->owner->id, 'created_at' => now()->subDays(3), 'expires_at' => now()->subDay()]);
    ReportShare::factory()->create();

    $this->get(route('tenant.compensation.scenarios.report', [$this->tenant, $this->scenario->id]))
        ->assertInertia(fn (Assert $page) => $page->where('shares.total', 6)->where('shares.per_page', 5)->has('shares.data', 5)
            ->where('shares.data.0.expiresAt', '5 nov 2026, 04:00'));
});

it('revokes a link and records who', function (): void {
    Log::spy();
    $share = ReportShare::factory()->create(['scenario_id' => $this->scenario->id, 'tenant_id' => $this->tenant->id, 'created_by' => $this->owner->id]);

    $this->delete(route('tenant.compensation.scenarios.shares.destroy', [$this->tenant, $this->scenario->id, $share->id]))->assertRedirect();

    expect($share->fresh()?->revoked_at)->not->toBeNull()
        ->and(AuditEntry::query()->where('event', 'compensation.share_revoked')->sole())->actor_id->toBe($this->owner->id)
        ->metadata->toEqual(['scenario_id' => $this->scenario->id, 'share_id' => $share->id]);
    Log::shouldHaveReceived('info')->with('compensation.share_revoked', ['scenario_id' => $this->scenario->id, 'share_id' => $share->id])->once();
});

it('answers 404 for the scenario or the link of another company, and 403 to whoever cannot manage', function (): void {
    $foreign = Scenario::factory()->create();
    $foreignShare = ReportShare::factory()->create(['scenario_id' => $foreign->id]);
    $share = ReportShare::factory()->create(['scenario_id' => $this->scenario->id, 'tenant_id' => $this->tenant->id, 'created_by' => $this->owner->id]);

    $this->post(shareUrl($this->tenant, $foreign), ['days' => '3'])->assertNotFound();
    $this->delete(route('tenant.compensation.scenarios.shares.destroy', [$this->tenant, $foreign->id, $foreignShare->id]))->assertNotFound();
    // A link of this company under another scenario's URL is not found either.
    $other = Scenario::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->delete(route('tenant.compensation.scenarios.shares.destroy', [$this->tenant, $other->id, $share->id]))->assertNotFound();

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));
    $this->post(shareUrl($this->tenant, $this->scenario), ['days' => '3'])->assertForbidden();
    $this->delete(route('tenant.compensation.scenarios.shares.destroy', [$this->tenant, $this->scenario->id, $share->id]))->assertForbidden();

    expect($share->fresh()?->revoked_at)->toBeNull();
});
