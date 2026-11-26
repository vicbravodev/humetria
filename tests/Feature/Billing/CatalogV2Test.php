<?php

declare(strict_types=1);

use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Catalog;
use Illuminate\Database\QueryException;

/*
 * Catalog v2 (2026-11-26): Enterprise is gone. Integral carries single sign-on at its same price; the assisted implementation
 * and the monthly accompaniment are hand-made charges of the subscription, not a plan. Everything else is v1 as it was.
 */
it('quotes new subscriptions with v2, in force from 2026-11-26', function (): void {
    expect(Catalog::current()->name())->toBe('v2')
        ->and(Catalog::current()->effectiveOn())->toBe('2026-11-26');
});

it('keeps every plan of v1 but Enterprise, and adds single sign-on to Integral without touching its price, centers or quotas', function (): void {
    $v1 = config()->array('billing.versions.v1.plans');
    $v2 = config()->array('billing.versions.v2.plans');

    $expected = $v1;
    unset($expected['enterprise']);
    $expected['integral']['modules'][] = 'sso';

    expect($v2)->toBe($expected);
});

it('keeps the rest of v1 as it was: VAT, extra centers, quotation and the channel', function (): void {
    $v1 = config()->array('billing.versions.v1');
    $v2 = config()->array('billing.versions.v2');

    unset($v1['plans'], $v1['effective_on'], $v2['plans'], $v2['effective_on']);

    expect($v2)->toBe($v1);
});

it('leaves v1 untouched, since a subscription keeps the version it was quoted with', function (): void {
    expect(config()->array('billing.versions.v1.plans'))->toHaveKey('enterprise')
        ->and(Catalog::version('v1')->effectiveOn())->toBe('2026-11-09');
});

it('does not let the database store the Enterprise plan', function (): void {
    $subscription = Subscription::factory()->create();

    expect(fn () => DB::table('subscriptions')->where('id', $subscription->id)->update(['plan' => 'enterprise']))
        ->toThrow(QueryException::class);
});
