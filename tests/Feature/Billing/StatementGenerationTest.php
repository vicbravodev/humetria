<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Billing\Actions\BuildStatement;
use App\Modules\Billing\Actions\GenerateStatement;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Enums\StatementStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Exceptions\InvalidStatement;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\StatementLine;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionCharge;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

mutates(BuildStatement::class, GenerateStatement::class);

function december(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-12-01');
}

/**
 * A direct company with a plan from a month before; 100 people and 2 centers of Cumplimiento unless said otherwise.
 *
 * @param  array<string, mixed>  $plan
 * @return array{0: Tenant, 1: Subscription}
 */
function billedCompany(array $plan = []): array
{
    [$tenant] = paidCompany();
    $subscription = Subscription::factory()->create(['tenant_id' => $tenant->id, 'starts_on' => '2026-11-01', 'renews_on' => '2026-12-01', ...$plan]);

    return [$tenant, $subscription];
}

it('makes the draft of a month from the quote: the plan line, then the VAT on its own line, with the whole snapshot', function (): void {
    Log::spy();
    [$tenant, $subscription] = billedCompany();
    $author = User::factory()->create();

    $statement = resolve(GenerateStatement::class)->handle($tenant->id, december(), $author);

    expect($statement->status)->toBe(StatementStatus::Draft)
        ->and($statement->tenant_id)->toBe($tenant->id)
        ->and($statement->period_start->toDateString())->toBe('2026-12-01')
        ->and($statement->period_end->toDateString())->toBe('2027-01-01')
        ->and($statement->currency)->toBe('MXN')
        ->and($statement->catalog_version)->toBe('v1')
        ->and($statement->vat_percent)->toBe(16)
        ->and([$statement->subtotal_cents, $statement->vat_cents, $statement->total_cents])->toBe([490_000, 78_400, 568_400])
        ->and($statement->created_by)->toBe($author->id)
        ->and($statement->issued_at)->toBeNull()
        ->and($statement->snapshot)->toBe([
            'subscription_id' => $subscription->id,
            'plan' => 'cumplimiento',
            'profile' => 'direct',
            'seats' => 100,
            'centers' => 2,
            'eligible_integral_companies' => 0,
            'negotiated_price_cents' => null,
            'proration' => null,
        ]);

    $lines = StatementLine::query()->where('statement_id', $statement->id)->orderBy('position')->get();
    expect($lines->pluck('code')->all())->toBe(['plan', 'vat'])
        ->and($lines->pluck('position')->all())->toBe([1, 2])
        ->and($lines->pluck('amount_cents')->all())->toBe([490_000, 78_400])
        ->and($lines[0]->explanation)->toBe('100 personas × $49 = $4,900')
        ->and($lines[1]->label)->toBe('IVA 16%')
        ->and($lines[1]->explanation)->toBe('16% sobre el subtotal de $4,900');
    Log::shouldHaveReceived('info')->once()->with('billing.statement_generated', ['tenant_id' => $tenant->id, 'period' => '2026-12', 'total_cents' => 568_400]);
});

it('takes every amount from the quote, VAT included, whatever the plan', function (array $plan, int $subtotal, int $vat): void {
    [$tenant] = billedCompany($plan);

    $statement = resolve(GenerateStatement::class)->handle($tenant->id, december(), User::factory()->create());

    expect([$statement->subtotal_cents, $statement->vat_cents, $statement->total_cents])->toBe([$subtotal, $vat, $subtotal + $vat]);
})->with([
    'direct Integral, 100 people, 4 centers: $10,049' => [['plan' => Plan::Integral, 'centers' => 4], 1_004_900, 160_784],
    'direct Jornada, 20 people: the minimum' => [['plan' => Plan::Jornada, 'seats' => 20, 'centers' => 1], 199_000, 31_840],
    'direct, 0 people: the minimum' => [['plan' => Plan::Cumplimiento, 'seats' => 0], 299_000, 47_840],
]);

it('gives a channel Integral company its share of the discount only when the account has three of them', function (): void {
    $account = App\Modules\Billing\Models\BillingAccount::factory()->consultant()->create();
    $integral = ['plan' => Plan::Integral, 'profile' => Profile::ChannelIntegral, 'seats' => 100, 'centers' => 3, 'starts_on' => '2026-11-01', 'renews_on' => '2026-12-01'];
    $tenants = [planFor($account, $integral), planFor($account, $integral)];

    $without = resolve(GenerateStatement::class)->handle($tenants[0]->id, december(), User::factory()->create());
    expect($without->subtotal_cents)->toBe(990_000);

    $tenants[] = planFor($account, $integral);
    $with = resolve(GenerateStatement::class)->handle($tenants[1]->id, december(), User::factory()->create());
    $codes = StatementLine::query()->where('statement_id', $with->id)->orderBy('position')->pluck('code')->all();

    expect($with->subtotal_cents)->toBe(792_000)
        ->and($with->vat_cents)->toBe(126_720)
        ->and($with->snapshot['eligible_integral_companies'])->toBe(3)
        ->and($codes)->toBe(['plan', 'channel_discount', 'vat']);
});

it('prorates the first month of a plan that starts in the middle of it, and only that one', function (): void {
    [$tenant] = billedCompany(['starts_on' => '2026-12-16', 'renews_on' => '2027-01-01']);

    $first = resolve(GenerateStatement::class)->handle($tenant->id, december(), User::factory()->create());
    $next = resolve(GenerateStatement::class)->handle($tenant->id, CarbonImmutable::parse('2027-01-01'), User::factory()->create());

    // 16 of 31 days of $4,900.
    expect($first->subtotal_cents)->toBe(252_903)
        ->and($first->vat_cents)->toBe(40_464)
        ->and($first->snapshot['proration'])->toBe(['days_remaining' => 16, 'period_days' => 31])
        ->and(StatementLine::query()->where('statement_id', $first->id)->where('code', 'plan')->sole()->explanation)->toContain('16 de 31 días')
        ->and($next->subtotal_cents)->toBe(490_000)
        ->and($next->snapshot['proration'])->toBeNull();
});

it('refuses a month before the plan starts, and a plan in trial or ended', function (): void {
    [$tenant] = billedCompany(['starts_on' => '2027-01-10', 'renews_on' => '2027-02-01']);
    [$trial] = billedCompany(['status' => SubscriptionStatus::Trial]);
    [$ended] = billedCompany(['status' => SubscriptionStatus::Ended]);
    $generate = fn (Tenant $company) => fn () => resolve(GenerateStatement::class)->handle($company->id, december(), User::factory()->create());

    expect($generate($tenant))->toThrow(InvalidStatement::class, 'La suscripción empieza después de ese periodo.')
        ->and($generate($trial))->toThrow(InvalidStatement::class, 'Una suscripción en prueba no se factura.')
        ->and($generate($ended))->toThrow(InvalidStatement::class, 'Una suscripción terminada ya no se factura.')
        ->and(Statement::query()->count())->toBe(0);
});

it('refuses a company with no plan or no payer, saying what to do first', function (): void {
    [$unplanned] = paidCompany();
    $unpaid = Tenant::factory()->create();
    Subscription::factory()->create(['tenant_id' => $unpaid->id, 'starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);
    $generate = fn (Tenant $company) => fn () => resolve(GenerateStatement::class)->handle($company->id, december(), User::factory()->create());

    expect($generate($unplanned))->toThrow(InvalidStatement::class, 'Asigna un plan a la empresa antes de hacerle un estado de cuenta.')
        ->and($generate($unpaid))->toThrow(InvalidStatement::class, 'Vincula la empresa a una cuenta antes de hacerle un estado de cuenta.');
});

it('cannot be made for a plan that needs a quotation, and writes nothing, not even a billed charge', function (): void {
    [$tenant, $subscription] = billedCompany(['seats' => 150]);
    $charge = SubscriptionCharge::factory()->for($subscription)->create(['tenant_id' => $tenant->id, 'charge_on' => '2026-12-10']);

    expect(fn () => resolve(GenerateStatement::class)->handle($tenant->id, december(), User::factory()->create()))
        ->toThrow(InvalidStatement::class, 'Esta suscripción requiere cotización: fija un precio negociado antes de hacer el estado de cuenta.');

    expect(Statement::query()->count())->toBe(0)->and(StatementLine::query()->count())->toBe(0)->and($charge->refresh()->billed_at)->toBeNull();
});

it('bills a plan above the limit that has a negotiated price in force', function (): void {
    [$tenant] = billedCompany(['seats' => 150, 'profile' => Profile::Negotiated, 'negotiated_price_cents' => 1_500_000]);

    $statement = resolve(GenerateStatement::class)->handle($tenant->id, december(), User::factory()->create());

    expect([$statement->subtotal_cents, $statement->vat_cents])->toBe([1_500_000, 240_000])
        ->and($statement->snapshot['negotiated_price_cents'])->toBe(1_500_000);
});

it('adds the recurring charges that are in force that month as lines of their own', function (): void {
    [$tenant, $subscription] = billedCompany();
    $make = fn (array $dates, string $name) => SubscriptionCharge::factory()->recurring()->for($subscription)->create(['tenant_id' => $tenant->id, 'description' => $name, 'scope' => 'Una sesión al mes', 'amount_cents' => 150_050, ...$dates]);
    $make(['charge_on' => '2026-11-15', 'ends_on' => null], 'Acompañamiento');
    $make(['charge_on' => '2026-10-01', 'ends_on' => '2026-11-30'], 'Terminado en noviembre');
    $make(['charge_on' => '2026-12-31', 'ends_on' => null], 'Empieza el último día');
    $make(['charge_on' => '2027-01-01', 'ends_on' => null], 'Empieza en enero');
    $make(['charge_on' => '2026-01-01', 'ends_on' => '2026-12-01'], 'Termina el primero');

    $statement = resolve(GenerateStatement::class)->handle($tenant->id, december(), User::factory()->create());
    $lines = StatementLine::query()->where('statement_id', $statement->id)->orderBy('position')->get();

    expect($lines->pluck('label')->all())->toBe(['Cumplimiento', 'Termina el primero', 'Acompañamiento', 'Empieza el último día', 'IVA 16%'])
        ->and($lines[2]->code)->toBe('charge_recurring')
        ->and($lines[2]->explanation)->toBe('Cargo recurrente desde 15 nov 2026: Una sesión al mes')
        ->and($statement->subtotal_cents)->toBe(490_000 + 3 * 150_050)
        ->and($statement->vat_cents)->toBe(Quote_divide((490_000 + 3 * 150_050) * 16, 100));
});

/** Half a cent up, as the quote does it. */
function Quote_divide(int $numerator, int $denominator): int
{
    return App\Modules\Billing\Support\Quote::divide($numerator, $denominator);
}

it('bills a one-time charge once: on the month it falls in, and never again', function (): void {
    [$tenant, $subscription] = billedCompany();
    $inDecember = SubscriptionCharge::factory()->for($subscription)->create(['tenant_id' => $tenant->id, 'charge_on' => '2026-12-31', 'amount_cents' => 1_250_000, 'description' => 'Implementación', 'scope' => 'Alta de 3 centros']);
    $inJanuary = SubscriptionCharge::factory()->for($subscription)->create(['tenant_id' => $tenant->id, 'charge_on' => '2027-01-01']);
    $before = SubscriptionCharge::factory()->for($subscription)->create(['tenant_id' => $tenant->id, 'charge_on' => '2026-11-30']);
    $already = SubscriptionCharge::factory()->for($subscription)->create(['tenant_id' => $tenant->id, 'charge_on' => '2026-12-10', 'billed_at' => '2026-12-11 10:00:00']);
    $this->freezeTime();

    $statement = resolve(GenerateStatement::class)->handle($tenant->id, december(), User::factory()->create());
    $january = resolve(GenerateStatement::class)->handle($tenant->id, CarbonImmutable::parse('2027-01-01'), User::factory()->create());

    $line = StatementLine::query()->where('statement_id', $statement->id)->where('code', 'charge_one_time')->sole();
    expect($line->charge_id)->toBe($inDecember->id)
        ->and($line->amount_cents)->toBe(1_250_000)
        ->and($line->explanation)->toBe('Cargo de una sola vez del 31 dic 2026: Alta de 3 centros')
        ->and($statement->subtotal_cents)->toBe(1_740_000)
        ->and($inDecember->refresh()->billed_at?->toIso8601String())->toBe(now()->toIso8601String())
        ->and($before->refresh()->billed_at)->toBeNull()
        ->and($already->refresh()->billed_at?->toDateTimeString())->toBe('2026-12-11 10:00:00')
        ->and(StatementLine::query()->where('statement_id', $january->id)->where('code', 'charge_one_time')->sole()->charge_id)->toBe($inJanuary->id);
});

it('is idempotent: making the same month twice returns the first statement and bills nothing twice', function (): void {
    [$tenant, $subscription] = billedCompany();
    $charge = SubscriptionCharge::factory()->for($subscription)->create(['tenant_id' => $tenant->id, 'charge_on' => '2026-12-10']);

    $first = resolve(GenerateStatement::class)->handle($tenant->id, december(), User::factory()->create());
    $billedAt = $charge->refresh()->billed_at;
    $again = resolve(GenerateStatement::class)->handle($tenant->id, CarbonImmutable::parse('2026-12-20'), User::factory()->create());

    expect($again->id)->toBe($first->id)
        ->and(Statement::query()->count())->toBe(1)
        ->and(StatementLine::query()->count())->toBe(3)
        ->and($charge->refresh()->billed_at?->equalTo($billedAt))->toBeTrue();
});

it('builds the lines for the preview without writing anything', function (): void {
    [$tenant, $subscription] = billedCompany();
    $charge = SubscriptionCharge::factory()->for($subscription)->create(['tenant_id' => $tenant->id, 'charge_on' => '2026-12-10']);

    $draft = resolve(BuildStatement::class)->handle($tenant->id, december());

    expect(array_map(fn ($line) => $line->code, $draft->lines))->toBe(['plan', 'charge_one_time', 'vat'])
        ->and([$draft->subtotalCents, $draft->vatCents, $draft->totalCents])->toBe([990_000, 158_400, 1_148_400])
        ->and(Statement::query()->count())->toBe(0)
        ->and($charge->refresh()->billed_at)->toBeNull();
});

it('does not change the company: no access, no dates, no restriction', function (): void {
    [$tenant] = billedCompany();
    $before = $tenant->refresh()->getAttributes();

    resolve(GenerateStatement::class)->handle($tenant->id, december(), User::factory()->create());

    expect($tenant->refresh()->getAttributes())->toBe($before)
        ->and(AuditEntry::query()->count())->toBe(0);
});
