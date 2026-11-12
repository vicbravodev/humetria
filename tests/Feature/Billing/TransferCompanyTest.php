<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Billing\Actions\BuildStatement;
use App\Modules\Billing\Actions\GenerateStatement;
use App\Modules\Billing\Actions\IssueStatement;
use App\Modules\Billing\Actions\PreviewTransfer;
use App\Modules\Billing\Actions\TransferCompany;
use App\Modules\Billing\Data\TransferData;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Exceptions\InvalidTransfer;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\CompanyTransfer;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Eligibility;
use App\Modules\Billing\Support\TransferGuard;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

mutates(TransferCompany::class, PreviewTransfer::class, TransferGuard::class, Eligibility::class, BuildStatement::class);

function transferTo(?BillingAccount $to, string $on = '2027-01-01', string $reason = 'El cliente contrata directo', ?Profile $profile = null): TransferData
{
    return new TransferData($to?->id, CarbonImmutable::parse($on), $reason, $profile);
}

it('moves a company to another payer from a cut: the old link ends, a new one opens, and the move is logged and audited', function (): void {
    Log::spy();
    [$tenant, $from] = paidCompany();
    $to = BillingAccount::factory()->create();
    $by = User::factory()->create();

    resolve(TransferCompany::class)->handle($tenant->id, transferTo($to), $by);

    $old = AccountCompany::query()->where('billing_account_id', $from->id)->sole();
    $new = AccountCompany::query()->where('billing_account_id', $to->id)->sole();
    expect($old->ends_on?->toDateString())->toBe('2027-01-01')
        ->and($new->tenant_id)->toBe($tenant->id)
        ->and($new->starts_on->toDateString())->toBe('2027-01-01')
        ->and($new->ends_on)->toBeNull()
        ->and(AccountCompany::query()->current()->where('tenant_id', $tenant->id)->sole()->billing_account_id)->toBe($to->id);
    $transfer = CompanyTransfer::query()->sole();
    expect($transfer->from_account_id)->toBe($from->id)
        ->and($transfer->to_account_id)->toBe($to->id)
        ->and($transfer->reason)->toBe('El cliente contrata directo')
        ->and($transfer->created_by)->toBe($by->id);
    Log::shouldHaveReceived('info')->with('billing.company_transferred', ['tenant_id' => $tenant->id, 'from' => $from->id, 'to' => $to->id, 'effective_on' => '2027-01-01']);
    expect(AuditEntry::query()->where('event', 'billing.company_transferred')->sole()->metadata)
        ->toEqualCanonicalizing(['tenant_id' => $tenant->id, 'from' => $from->id, 'to' => $to->id, 'reason' => 'El cliente contrata directo', 'effective_on' => '2027-01-01', 'profile_before' => null, 'profile_after' => null]);
});

it('can leave a company with no payer, and keeps the history', function (): void {
    [$tenant, $from] = paidCompany();

    resolve(TransferCompany::class)->handle($tenant->id, transferTo(null), User::factory()->create());

    expect(AccountCompany::query()->current()->where('tenant_id', $tenant->id)->exists())->toBeFalse()
        ->and(AccountCompany::query()->where('billing_account_id', $from->id)->sole()->ends_on?->toDateString())->toBe('2027-01-01')
        ->and(CompanyTransfer::query()->sole()->to_account_id)->toBeNull();
});

it('grants and removes no access: memberships and the company are exactly as they were', function (): void {
    [$tenant] = paidCompany('consultant');
    $consultant = memberOf($tenant, TenantRole::Hr);
    $payerStaff = User::factory()->create();
    $before = TenantMembership::query()->orderBy('id')->get(['id', 'user_id', 'tenant_id'])->toArray();
    $attributes = Tenant::query()->findOrFail($tenant->id)->getAttributes();

    resolve(TransferCompany::class)->handle($tenant->id, transferTo(BillingAccount::factory()->create()), User::factory()->create());

    expect(TenantMembership::query()->orderBy('id')->get(['id', 'user_id', 'tenant_id'])->toArray())->toBe($before)
        ->and(Tenant::query()->findOrFail($tenant->id)->getAttributes())->toBe($attributes);
    $this->actingAs($consultant)->get(route('tenant.dashboard', $tenant))->assertOk();
    $this->actingAs($payerStaff)->get(route('tenant.dashboard', $tenant))->assertNotFound();
});

it('leaves issued statements and their balance with the original account and sends the next ones to the new payer', function (): void {
    [$tenant, $from] = paidCompany();
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);
    $december = resolve(IssueStatement::class)->handle(resolve(GenerateStatement::class)->handle($tenant->id, statementMonth(), User::factory()->create()), User::factory()->create());
    $to = BillingAccount::factory()->create();

    resolve(TransferCompany::class)->handle($tenant->id, transferTo($to), User::factory()->create());

    $january = resolve(GenerateStatement::class)->handle($tenant->id, CarbonImmutable::parse('2027-01-01'), User::factory()->create());
    expect($december->refresh()->billing_account_id)->toBe($from->id)
        ->and($january->billing_account_id)->toBe($to->id)
        ->and(Statement::query()->where('billing_account_id', $from->id)->count())->toBe(1);
});

it('bills a month before the cut to the payer it had then, even if it is made after the move', function (): void {
    [$tenant, $from] = paidCompany();
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);
    resolve(TransferCompany::class)->handle($tenant->id, transferTo(BillingAccount::factory()->create(), '2027-01-01'), User::factory()->create());

    $december = resolve(GenerateStatement::class)->handle($tenant->id, statementMonth(), User::factory()->create());

    expect($december->billing_account_id)->toBe($from->id);
});

it('keeps the subscription with the company and prices it again only where the destination cannot hold it', function (): void {
    [$tenant] = paidCompany('consultant');
    $subscription = Subscription::factory()->plan(Plan::Cumplimiento, Profile::ChannelCompliance)->create(['tenant_id' => $tenant->id]);
    $direct = BillingAccount::factory()->create();

    expect(fn () => resolve(TransferCompany::class)->handle($tenant->id, transferTo($direct), User::factory()->create()))
        ->toThrow(InvalidTransfer::class, 'La cuenta de destino no admite Canal Cumplimiento: elige cómo se cobrará la empresa.');
    expect(AccountCompany::query()->current()->where('tenant_id', $tenant->id)->sole()->ends_on)->toBeNull()
        ->and(CompanyTransfer::query()->count())->toBe(0);

    resolve(TransferCompany::class)->handle($tenant->id, transferTo($direct, profile: Profile::Direct), User::factory()->create());

    expect($subscription->refresh()->profile)->toBe(Profile::Direct)
        ->and($subscription->plan)->toBe(Plan::Cumplimiento)
        ->and($subscription->seats)->toBe(100)
        ->and(CompanyTransfer::query()->sole()->profile_before)->toBe('channel_compliance')
        ->and(CompanyTransfer::query()->sole()->profile_after)->toBe('direct');
});

it('keeps the profile when the destination can hold it', function (): void {
    [$tenant] = paidCompany('consultant');
    $subscription = Subscription::factory()->plan(Plan::Cumplimiento, Profile::ChannelCompliance)->create(['tenant_id' => $tenant->id]);

    resolve(TransferCompany::class)->handle($tenant->id, transferTo(BillingAccount::factory()->consultant()->create()), User::factory()->create());

    expect($subscription->refresh()->profile)->toBe(Profile::ChannelCompliance);
});

it('refuses what the rules refuse, writes nothing and says which field', function (Closure $data, string $field): void {
    [$tenant, $from] = paidCompany();
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);
    resolve(GenerateStatement::class)->handle($tenant->id, statementMonth(), User::factory()->create());
    $other = BillingAccount::factory()->create();

    try {
        resolve(TransferCompany::class)->handle($tenant->id, $data($other, $from), User::factory()->create());
        $thrown = null;
    } catch (InvalidTransfer $exception) {
        $thrown = $exception;
    }

    expect($thrown?->field)->toBe($field)
        ->and(CompanyTransfer::query()->count())->toBe(0)
        ->and(AccountCompany::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(AccountCompany::query()->sole()->ends_on)->toBeNull();
})->with([
    'the same account' => [fn ($other, $from) => transferTo($from), 'to'],
    'an account that does not exist' => [fn () => new TransferData('11111111-1111-4111-8111-111111111111', CarbonImmutable::parse('2027-01-01'), 'x'), 'to'],
    'a day that is not a cut' => [fn ($other) => transferTo($other, '2027-01-15'), 'effective_on'],
    'a month that already has a statement' => [fn ($other) => transferTo($other, '2026-12-01'), 'effective_on'],
    'before the link began' => [fn ($other) => transferTo($other, '2026-11-01'), 'effective_on'],
    'no reason' => [fn ($other) => transferTo($other, reason: '  '), 'reason'],
]);

it('refuses a company that has no payer', function (): void {
    $tenant = Tenant::factory()->create();

    expect(fn () => resolve(TransferCompany::class)->handle($tenant->id, transferTo(BillingAccount::factory()->create()), User::factory()->create()))
        ->toThrow(InvalidTransfer::class, 'La empresa no tiene un pagador vigente.');
});

it('previews the new quote and who loses or gains the channel discount, writing nothing', function (): void {
    $from = BillingAccount::factory()->consultant()->create();
    $to = BillingAccount::factory()->consultant()->create();
    $moving = planFor($from, ['plan' => Plan::Integral, 'profile' => Profile::ChannelIntegral]);
    foreach ([1, 2] as $_) {
        planFor($from, ['plan' => Plan::Integral, 'profile' => Profile::ChannelIntegral]);
        planFor($to, ['plan' => Plan::Integral, 'profile' => Profile::ChannelIntegral]);
    }
    planFor($to, ['plan' => Plan::Integral, 'profile' => Profile::ChannelIntegral]);
    // from: 3 Integral companies (discount) → 2 without the moving one; to: 3 → 4 with it.

    $preview = resolve(PreviewTransfer::class)->handle($moving->id, transferTo($to));

    expect($preview['from']['name'])->toBe($from->legal_name)
        ->and($preview['to']['name'])->toBe($to->legal_name)
        ->and($preview['effects'][0])->toMatchArray(['role' => 'from', 'before' => 3, 'after' => 2, 'effect' => 'loses'])
        ->and($preview['effects'][1])->toMatchArray(['role' => 'to', 'before' => 3, 'after' => 4, 'effect' => 'same'])
        ->and($preview['current']['total'])->toBe($preview['quote']['total'])
        ->and($preview['access'])->toBe('Trasladar una empresa no da ni quita acceso a nadie: las membresías no se tocan.')
        ->and(CompanyTransfer::query()->count())->toBe(0)
        ->and(AccountCompany::query()->current()->where('tenant_id', $moving->id)->sole()->billing_account_id)->toBe($from->id);
});

it('previews who gains the discount and the price change of a new profile', function (): void {
    $from = BillingAccount::factory()->consultant()->create();
    $to = BillingAccount::factory()->consultant()->create();
    $moving = planFor($from, ['plan' => Plan::Integral, 'profile' => Profile::ChannelIntegral]);
    planFor($to, ['plan' => Plan::Integral, 'profile' => Profile::ChannelIntegral]);
    planFor($to, ['plan' => Plan::Integral, 'profile' => Profile::ChannelIntegral]);

    $preview = resolve(PreviewTransfer::class)->handle($moving->id, transferTo($to));

    expect($preview['effects'][1])->toMatchArray(['role' => 'to', 'before' => 2, 'after' => 3, 'effect' => 'gains'])
        ->and($preview['effects'][1]['text'])->toBe("{$to->legal_name} gana el descuento de canal Integral desde la renovación del 1 ene 2027.")
        ->and($preview['quote']['total'])->not->toBe($preview['current']['total']);

    $toDirect = resolve(PreviewTransfer::class)->handle($moving->id, transferTo(BillingAccount::factory()->create(), profile: Profile::Direct));
    expect($toDirect['effects'])->toHaveCount(1)
        ->and($toDirect['quote']['total'])->not->toBeNull();
});

it('previews the move of a company with no plan without a quote', function (): void {
    [$tenant] = paidCompany();

    $preview = resolve(PreviewTransfer::class)->handle($tenant->id, transferTo(null));

    expect($preview['quote'])->toBeNull()->and($preview['current'])->toBeNull()->and($preview['to'])->toBeNull();
});
