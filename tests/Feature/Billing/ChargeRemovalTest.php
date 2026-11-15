<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Billing\Actions\BuildStatement;
use App\Modules\Billing\Actions\EndCharge;
use App\Modules\Billing\Actions\RemoveCharge;
use App\Modules\Billing\Http\Requests\EndChargeRequest;
use App\Modules\Billing\Http\Requests\RemoveChargeRequest;
use App\Modules\Billing\Models\SubscriptionCharge;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;

mutates(EndCharge::class, RemoveCharge::class, EndChargeRequest::class, RemoveChargeRequest::class);

/** A charge of a company with a plan, in the statement company's subscription. */
function removableCharge(string $state = 'one_time', array $attributes = []): SubscriptionCharge
{
    [$tenant, $subscription] = statementCompany();

    return SubscriptionCharge::query()->create([
        'subscription_id' => $subscription->id, 'tenant_id' => $tenant->id,
        'kind' => $state, 'description' => 'Acompañamiento', 'scope' => 'Toda la empresa', 'amount_cents' => 150_000,
        'charge_on' => '2026-11-01', 'approved_by' => User::factory()->create()->id, ...$attributes,
    ]);
}

beforeEach(function (): void {
    $this->travelTo('2026-11-26 15:00:00');
});

it('lives under /admin/billing/charges', function (): void {
    expect(route('billing.charges.end', 'abc', absolute: false))->toBe('/admin/billing/charges/abc/end')
        ->and(route('billing.charges.destroy', 'abc', absolute: false))->toBe('/admin/billing/charges/abc');
});

describe('ending a recurring charge', function (): void {
    it('sets its last day, with the reason, and logs and audits it', function (): void {
        Log::spy();
        $charge = removableCharge('recurring');

        $this->actingAs(superadmin())->put(route('billing.charges.end', $charge), ['ends_on' => '2026-12-31', 'reason' => 'El cliente termina el acompañamiento'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Cargo terminado.']]);

        expect($charge->refresh()->ends_on?->toDateString())->toBe('2026-12-31');
        Log::shouldHaveReceived('info')->once()->with('billing.charge_ended', ['tenant_id' => $charge->tenant_id, 'ends_on' => '2026-12-31']);
        $entry = AuditEntry::query()->where('event', 'billing.charge_ended')->sole();
        expect($entry->subject_id)->toBe($charge->id)->and($entry->metadata)->toEqualCanonicalizing(['tenant_id' => $charge->tenant_id, 'ends_on' => '2026-12-31', 'reason' => 'El cliente termina el acompañamiento']);
    });

    it('stops billing it from the month after its last day, and still bills the month it ends in', function (): void {
        $charge = removableCharge('recurring', ['charge_on' => '2026-10-01']);
        $this->actingAs(superadmin())->put(route('billing.charges.end', $charge), ['ends_on' => '2026-12-31', 'reason' => 'Fin'])->assertSessionHasNoErrors();

        $codes = fn (string $month): array => array_map(fn ($line) => $line->code, resolve(BuildStatement::class)->handle($charge->tenant_id, CarbonImmutable::parse($month))->lines);

        expect($codes('2026-12-01'))->toContain('charge_recurring')->and($codes('2027-01-01'))->not->toContain('charge_recurring');
    });

    it('refuses what cannot be ended, saying why', function (callable $make, array $input, string $field): void {
        $charge = $make();

        $this->actingAs(superadmin())->put(route('billing.charges.end', $charge), $input)->assertSessionHasErrors($field);

        expect($charge->refresh()->ends_on?->toDateString())->toBe($charge->ends_on?->toDateString());
    })->with([
        'a one-time charge' => [fn () => removableCharge('one_time'), ['ends_on' => '2026-12-31', 'reason' => 'x'], 'kind'],
        'one that already ends' => [fn () => removableCharge('recurring', ['ends_on' => '2026-12-31']), ['ends_on' => '2026-11-30', 'reason' => 'x'], 'ends_on'],
        'a day before it began' => [fn () => removableCharge('recurring'), ['ends_on' => '2026-10-31', 'reason' => 'x'], 'ends_on'],
        'without reason' => [fn () => removableCharge('recurring'), ['ends_on' => '2026-12-31', 'reason' => '  '], 'reason'],
        'a reason in two lines' => [fn () => removableCharge('recurring'), ['ends_on' => '2026-12-31', 'reason' => "a\nb"], 'reason'],
        'a day that is not one' => [fn () => removableCharge('recurring'), ['ends_on' => '31/12/2026', 'reason' => 'x'], 'ends_on'],
    ]);
});

describe('removing a one-time charge nobody billed yet', function (): void {
    it('deletes it, with the reason, and logs and audits what it was', function (): void {
        Log::spy();
        $charge = removableCharge('one_time');

        $this->actingAs(superadmin())->delete(route('billing.charges.destroy', $charge), ['reason' => 'Se cobró por otro medio'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Cargo quitado.']]);

        expect(SubscriptionCharge::query()->count())->toBe(0);
        Log::shouldHaveReceived('info')->once()->with('billing.charge_removed', ['tenant_id' => $charge->tenant_id, 'kind' => 'one_time']);
        expect(AuditEntry::query()->where('event', 'billing.charge_removed')->sole()->metadata)->toEqualCanonicalizing(['tenant_id' => $charge->tenant_id, 'kind' => 'one_time', 'amount_cents' => 150_000, 'reason' => 'Se cobró por otro medio']);
    });

    it('does not remove one that was billed, nor a recurring one (that one ends)', function (callable $make): void {
        $charge = $make();

        $this->actingAs(superadmin())->delete(route('billing.charges.destroy', $charge), ['reason' => 'x'])->assertSessionHasErrors('charge');

        expect(SubscriptionCharge::query()->count())->toBe(1);
    })->with([
        'billed' => [fn () => removableCharge('one_time', ['billed_at' => '2026-11-20 10:00:00'])],
        'recurring' => [fn () => removableCharge('recurring')],
    ]);

    it('needs a reason', function (): void {
        $charge = removableCharge('one_time');

        $this->actingAs(superadmin())->delete(route('billing.charges.destroy', $charge), ['reason' => ''])->assertSessionHasErrors('reason');

        expect(SubscriptionCharge::query()->count())->toBe(1);
    });

    it('is not billed in the next statement', function (): void {
        $charge = removableCharge('one_time', ['charge_on' => '2026-12-05']);
        $this->actingAs(superadmin())->delete(route('billing.charges.destroy', $charge), ['reason' => 'No aplica'])->assertSessionHasNoErrors();

        $codes = array_map(fn ($line) => $line->code, resolve(BuildStatement::class)->handle($charge->tenant_id, CarbonImmutable::parse('2026-12-01'))->lines);

        expect($codes)->not->toContain('charge_one_time');
    });
});

it('is the superadmin\'s alone: support, a member and a guest get nothing', function (): void {
    $charge = removableCharge('recurring');

    $this->put(route('billing.charges.end', $charge), ['ends_on' => '2026-12-31', 'reason' => 'x'])->assertRedirect(route('login'));
    foreach ([support(), User::factory()->create()] as $user) {
        $this->actingAs($user)->put(route('billing.charges.end', $charge), ['ends_on' => '2026-12-31', 'reason' => 'x'])->assertNotFound();
        $this->actingAs($user)->delete(route('billing.charges.destroy', $charge), ['reason' => 'x'])->assertNotFound();
    }

    expect($charge->refresh()->ends_on)->toBeNull()->and(SubscriptionCharge::query()->count())->toBe(1);
});

it('answers 404 for a charge that does not exist', function (): void {
    $this->actingAs(superadmin())->put(route('billing.charges.end', '11111111-1111-4111-8111-111111111111'), ['ends_on' => '2026-12-31', 'reason' => 'x'])->assertNotFound();
    $this->actingAs(superadmin())->delete(route('billing.charges.destroy', '11111111-1111-4111-8111-111111111111'), ['reason' => 'x'])->assertNotFound();
});

it('tells the account page which charges a statement already billed', function (): void {
    $billed = removableCharge('one_time', ['billed_at' => '2026-11-20 10:00:00', 'charge_on' => '2026-11-20']);
    $open = SubscriptionCharge::factory()->for($billed->subscription)->create(['tenant_id' => $billed->tenant_id, 'charge_on' => '2026-11-21']);
    $link = App\Modules\Billing\Models\AccountCompany::query()->where('tenant_id', $billed->tenant_id)->sole();

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $link->billing_account_id))->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
        ->where('charges.data.0.id', $open->id)->where('charges.data.0.billed', false)
        ->where('charges.data.1.id', $billed->id)->where('charges.data.1.billed', true));
});
