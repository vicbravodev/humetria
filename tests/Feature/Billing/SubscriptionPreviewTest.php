<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Billing\Actions\PreviewSubscription;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Http\Requests\PreviewSubscriptionRequest;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\SubscriptionGuard;
use App\Modules\Tenancy\Models\Tenant;

mutates(PreviewSubscription::class, PreviewSubscriptionRequest::class, SubscriptionGuard::class);

it('lives under /admin/billing/companies/{company}/subscription/preview', function (): void {
    expect(route('billing.subscriptions.preview', 'abc', absolute: false))->toBe('/admin/billing/companies/abc/subscription/preview');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function previewInput(array $overrides = []): array
{
    return subscriptionInput(['profile' => 'channel_compliance', 'seats' => 50, ...$overrides]);
}

it('shows the next amount before saving: channel 50 to 51 people goes from $2,490 to $3,990', function (): void {
    [$tenant] = paidCompany('consultant');
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'plan' => Plan::Cumplimiento, 'profile' => Profile::ChannelCompliance, 'seats' => 50, 'centers' => 2]);

    $this->actingAs(superadmin())->postJson(route('billing.subscriptions.preview', $tenant->id), previewInput(['seats' => 51]))
        ->assertOk()
        ->assertJsonPath('current.subtotal', '$2,490')
        ->assertJsonPath('quote.subtotal', '$3,990')
        ->assertJsonPath('quote.vat', '$638.40')
        ->assertJsonPath('quote.total', '$4,628.40')
        ->assertJsonPath('quote.requires_quotation', false)
        ->assertJsonPath('quote.lines.0.explanation', 'Banda de canal hasta 100 personas: $3,990')
        ->assertJsonPath('first_period', null)
        ->assertJsonPath('eligibility', null);
});

it('has no current amount for a company without a plan', function (): void {
    [$tenant] = paidCompany('consultant');

    $this->actingAs(superadmin())->postJson(route('billing.subscriptions.preview', $tenant->id), previewInput())
        ->assertOk()
        ->assertJsonPath('current', null)
        ->assertJsonPath('quote.subtotal', '$2,490');
});

it('writes nothing: no plan, no audit entry', function (): void {
    [$tenant] = paidCompany('consultant');

    $this->actingAs(superadmin())->postJson(route('billing.subscriptions.preview', $tenant->id), previewInput())->assertOk();

    expect(Subscription::query()->count())->toBe(0)->and(AuditEntry::query()->count())->toBe(0);
});

it('asks for a quotation, with no amount, above the limit of the profile', function (): void {
    [$tenant] = paidCompany('consultant');

    $this->actingAs(superadmin())->postJson(route('billing.subscriptions.preview', $tenant->id), previewInput(['seats' => 251]))
        ->assertOk()
        ->assertJsonPath('quote.requires_quotation', true)
        ->assertJsonPath('quote.subtotal', null)
        ->assertJsonPath('quote.explanation', 'Más de 250 personas: requiere cotización');
});

it('prices the first month in part when the plan starts after the 1st', function (): void {
    [$tenant] = paidCompany();

    $this->actingAs(superadmin())->postJson(route('billing.subscriptions.preview', $tenant->id), subscriptionInput(['starts_on' => '2026-12-16']))
        ->assertOk()
        ->assertJsonPath('quote.subtotal', '$4,900')
        // 4,900.00 × 16 / 31 days.
        ->assertJsonPath('first_period.subtotal', '$2,529.03')
        ->assertJsonPath('first_period.lines.0.explanation', '100 personas × $49 = $4,900, 16 de 31 días');
});

it('prices with a negotiated price in force, and says the table beyond the limit when it has expired', function (): void {
    [$tenant] = paidCompany();
    $until = now()->addMonth()->toDateString();

    $this->actingAs(superadmin())->postJson(route('billing.subscriptions.preview', $tenant->id), subscriptionInput(['profile' => 'negotiated', 'seats' => 180, 'negotiated_price' => '8000', 'negotiated_valid_until' => $until]))
        ->assertOk()->assertJsonPath('quote.subtotal', '$8,000');

    $this->postJson(route('billing.subscriptions.preview', $tenant->id), subscriptionInput(['profile' => 'direct', 'seats' => 180, 'negotiated_price' => '8000', 'negotiated_valid_until' => now()->subDay()->toDateString(), 'starts_on' => now()->subMonth()->toDateString()]))
        ->assertOk()->assertJsonPath('quote.requires_quotation', true);
});

it('counts this company for the Integral discount while it is being priced, and says why it applies', function (): void {
    $account = BillingAccount::factory()->consultant()->create();
    foreach (range(1, 2) as $ignored) {
        planFor($account, ['plan' => Plan::Integral, 'profile' => Profile::ChannelIntegral, 'centers' => 3]);
    }
    $third = Tenant::factory()->create();
    AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $third->id]);
    $input = previewInput(['plan' => 'integral', 'profile' => 'channel_integral', 'seats' => 100, 'centers' => 3]);

    $this->actingAs(superadmin())->postJson(route('billing.subscriptions.preview', $third->id), $input)
        ->assertOk()
        ->assertJsonPath('quote.subtotal', '$7,920')
        ->assertJsonPath('eligibility.applies', true);

    // As a trial it would not count: the other two stay short of three.
    $this->postJson(route('billing.subscriptions.preview', $third->id), [...$input, 'status' => 'trial'])
        ->assertOk()
        ->assertJsonPath('quote.subtotal', '$9,900')
        ->assertJsonPath('eligibility.applies', false);
});

it('does not count the plan the company has now, but the one being priced', function (): void {
    $account = BillingAccount::factory()->consultant()->create();
    foreach (range(1, 2) as $ignored) {
        planFor($account, ['plan' => Plan::Integral, 'profile' => Profile::ChannelIntegral, 'centers' => 3]);
    }
    $third = planFor($account, ['plan' => Plan::Integral, 'profile' => Profile::ChannelIntegral, 'centers' => 3, 'status' => SubscriptionStatus::Active]);
    $input = previewInput(['plan' => 'integral', 'profile' => 'channel_integral', 'seats' => 100, 'centers' => 3, 'status' => 'trial']);

    $this->actingAs(superadmin())->postJson(route('billing.subscriptions.preview', $third->id), $input)
        ->assertOk()
        ->assertJsonPath('current.subtotal', '$7,920')
        ->assertJsonPath('quote.subtotal', '$9,900')
        ->assertJsonPath('eligibility.applies', false);
});

it('refuses with the same reasons as saving, as errors the form can show', function (array $overrides, string $field): void {
    [$tenant] = paidCompany();

    $this->actingAs(superadmin())->postJson(route('billing.subscriptions.preview', $tenant->id), subscriptionInput($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'a channel profile on a direct account' => [['profile' => 'channel_compliance'], 'profile'],
    'seats below zero' => [['seats' => -1], 'seats'],
    'no centers' => [['centers' => 0], 'centers'],
    'an activation with no people' => [['seats' => 0], 'seats'],
]);

it('refuses a company that nobody pays', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(superadmin())->postJson(route('billing.subscriptions.preview', $tenant->id), subscriptionInput())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('plan');
});

it('answers 404 for a company that does not exist', function (): void {
    $this->actingAs(superadmin())->postJson(route('billing.subscriptions.preview', '11111111-1111-4111-8111-111111111111'), subscriptionInput())->assertNotFound();
});

it('is the superadmin\'s alone', function (): void {
    [$tenant] = paidCompany();

    $this->actingAs(support())->postJson(route('billing.subscriptions.preview', $tenant->id), subscriptionInput())->assertNotFound();
    $this->actingAs(ownerOf($tenant))->postJson(route('billing.subscriptions.preview', $tenant->id), subscriptionInput())->assertNotFound();
});

it('is throttled, so a form that is typed fast cannot flood the server', function (): void {
    [$tenant] = paidCompany();
    $this->actingAs(superadmin());

    foreach (range(1, 60) as $ignored) {
        $this->postJson(route('billing.subscriptions.preview', $tenant->id), subscriptionInput())->assertOk();
    }

    $this->postJson(route('billing.subscriptions.preview', $tenant->id), subscriptionInput())->assertStatus(429);
});

it('keeps the preview out of reach while impersonating', function (): void {
    expect(App\Http\Middleware\ForbidWhileImpersonating::guards('billing.subscriptions.preview'))->toBeTrue();
});
