<?php

declare(strict_types=1);

/*
 * The landing says what Humetría costs (resources/js/components/landing/landing-pricing.ts). The numbers live in the
 * commercial catalog (config/billing.php): the page is pinned to it, so a price changed in one place and not in the
 * other fails here instead of publishing a figure nobody charges.
 */
function pricingText(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/components/landing/landing-pricing.ts');
}

function landingCatalogKey(): string
{
    return 'billing.versions.'.config()->string('billing.current_version');
}

function landingPlansKey(): string
{
    return landingCatalogKey().'.plans';
}

function landingPesos(int $cents): string
{
    return '$'.number_format(intdiv($cents, 100));
}

it('holds the rate per person and the minimum of every plan, in pesos, exactly as the catalog charges them', function (string $plan): void {
    $catalog = config()->array(landingPlansKey().".{$plan}");

    expect(pricingText())->toContain('rate: '.intdiv($catalog['rate_cents'], 100).',')
        ->toContain('minimum: '.intdiv($catalog['minimum_cents'], 100).',');
})->with(['jornada', 'cumplimiento', 'integral']);

it('quotes by hand from the same headcount as the catalog and includes the same centers per plan', function (): void {
    $version = config()->array(landingCatalogKey());

    expect(pricingText())->toContain('QUOTE_ABOVE = '.$version['quotation_above']['direct'])
        ->toContain('centers: '.$version['plans']['jornada']['included_centers'].',')
        ->toContain('centers: '.$version['plans']['cumplimiento']['included_centers'].',')
        ->toContain('centers: '.$version['plans']['integral']['included_centers'].',');
});

it('says the prices are in pesos plus VAT, what an extra center costs and from how many people it is quoted by hand', function (): void {
    $version = config()->array(landingCatalogKey());
    $text = pricingText();

    expect($text)->toContain('más IVA')->toContain('pesos mexicanos')
        ->toContain(landingPesos($version['extra_center_cents']))
        ->toContain('más de '.$version['quotation_above']['direct'].' personas')
        ->toContain('MXN')
        ->and(config()->string('billing.currency'))->toBe('MXN');
});

it('has no Enterprise plan, and says the assisted implementation and the monthly accompaniment are quoted apart, promising no response time', function (): void {
    expect(pricingText())->toContain('Inicio de sesión único (SSO)')
        ->toContain('La implementación asistida y el acompañamiento mensual se cotizan aparte')
        ->not->toContain('Enterprise')->not->toContain('enterprise')
        ->not->toContain('SLA')->not->toContain('24 horas')->not->toContain('prioritari');
});

it('lists for every plan exactly the modules the catalog gives it, so the comparison table cannot promise what a plan lacks', function (string $plan): void {
    preg_match("/id: '{$plan}',.*?modules: \[([^\]]*)\]/s", pricingText(), $match);

    $listed = array_values(array_filter(array_map(fn (string $key): string => trim($key, " '\n"), explode(',', $match[1] ?? ''))));
    $catalog = config()->array(landingPlansKey().".{$plan}.modules");
    sort($listed);
    sort($catalog);

    expect($listed)->toBe($catalog);
})->with(['jornada', 'cumplimiento', 'integral']);

it('says Compensación can also be hired alone, quoted by hand, and the catalog backs it: it is in Integral only', function (): void {
    $plans = config()->array(landingPlansKey());

    expect(pricingText())->toContain('Compensación también se contrata sola')
        ->and($plans['integral']['modules'])->toContain('compensation')
        ->and($plans['jornada']['modules'])->not->toContain('compensation')
        ->and($plans['cumplimiento']['modules'])->not->toContain('compensation');
});
