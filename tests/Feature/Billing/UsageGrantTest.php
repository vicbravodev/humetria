<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Billing\Actions\GrantUsage;
use App\Modules\Billing\Contracts\UsageMeter;
use App\Modules\Billing\Http\Requests\GrantUsageRequest;
use App\Modules\Billing\Models\UsageGrant;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;

mutates(GrantUsage::class, GrantUsageRequest::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-18 18:00:00');
    config(['billing.unplanned_quotas.assistant_tokens' => 9000]);
    $this->tenant = Tenant::factory()->create();
});

it('lives under /admin/billing/companies', function (): void {
    expect(route('billing.usage.grant', 'abc', absolute: false))->toBe('/admin/billing/companies/abc/usage-grants');
});

it('gives the company extra tokens for the month with the reason, logs it and audits it', function (): void {
    Log::spy();
    $admin = superadmin();

    $this->actingAs($admin)->post(route('billing.usage.grant', $this->tenant->id), ['quantity' => '50000', 'reason' => 'Piloto de la comisión'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('companies.show', $this->tenant->id))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Tokens agregados al mes.']]);

    $grant = UsageGrant::query()->sole();

    expect($grant->only(['tenant_id', 'meter', 'quantity', 'reason', 'granted_by']))->toBe(['tenant_id' => $this->tenant->id, 'meter' => 'assistant_tokens', 'quantity' => 50_000, 'reason' => 'Piloto de la comisión', 'granted_by' => $admin->id])
        ->and($grant->period_start->toDateString())->toBe('2026-11-01')
        ->and(resolve(UsageMeter::class)->snapshot($this->tenant->id, 'assistant_tokens')->quota)->toBe(59_000)
        ->and(AuditEntry::query()->where('event', 'billing.usage_granted')->sole()->metadata)->toEqualCanonicalizing(['tenant_id' => $this->tenant->id, 'meter' => 'assistant_tokens', 'quantity' => 50_000, 'reason' => 'Piloto de la comisión', 'period_start' => '2026-11-01']);
    Log::shouldHaveReceived('info')->with('billing.usage_granted', ['tenant_id' => $this->tenant->id, 'meter' => 'assistant_tokens', 'quantity' => 50_000])->once();
});

it('is the superadmin\'s alone: support, a member and a guest get nothing', function (): void {
    foreach ([support(), ownerOf($this->tenant)] as $user) {
        $this->actingAs($user)->post(route('billing.usage.grant', $this->tenant->id), ['quantity' => '1000', 'reason' => 'x'])->assertNotFound();
    }

    auth()->logout();
    $this->post(route('billing.usage.grant', $this->tenant->id), ['quantity' => '1000', 'reason' => 'x'])->assertRedirect(route('login'));

    expect(UsageGrant::query()->count())->toBe(0);
});

it('answers 404 for a company that does not exist', function (): void {
    $this->actingAs(superadmin())->post(route('billing.usage.grant', (string) Illuminate\Support\Str::uuid()), ['quantity' => '1000', 'reason' => 'x'])->assertNotFound();
});

it('refuses what makes no sense, saying on which field', function (array $input, string $field): void {
    $this->actingAs(superadmin())->post(route('billing.usage.grant', $this->tenant->id), $input)->assertSessionHasErrors($field);

    expect(UsageGrant::query()->count())->toBe(0);
})->with([
    'no reason' => [['quantity' => '1000', 'reason' => ''], 'reason'],
    'only spaces' => [['quantity' => '1000', 'reason' => '   '], 'reason'],
    'a reason of several lines' => [['quantity' => '1000', 'reason' => "uno\ndos"], 'reason'],
    'zero' => [['quantity' => '0', 'reason' => 'x'], 'quantity'],
    'negative' => [['quantity' => '-5', 'reason' => 'x'], 'quantity'],
    'not a number' => [['quantity' => 'mucho', 'reason' => 'x'], 'quantity'],
    'a decimal' => [['quantity' => '10.5', 'reason' => 'x'], 'quantity'],
    'too many' => [['quantity' => '100000001', 'reason' => 'x'], 'quantity'],
]);

it('accepts the largest grant', function (): void {
    $this->actingAs(superadmin())->post(route('billing.usage.grant', $this->tenant->id), ['quantity' => '100000000', 'reason' => 'x'])->assertSessionHasNoErrors();
});
