<?php

declare(strict_types=1);

/*
 * The landing says the same thing the same way. These pin the copy decisions of its audit: one name for the demo call to
 * action, and a FAQ that agrees with the plans the page sells.
 */
function landingSources(): array
{
    $root = dirname(__DIR__, 2).'/resources/js';

    return [...glob($root.'/components/landing/*.tsx'), ...glob($root.'/pages/*.tsx')];
}

function landingSource(string $file): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/components/landing/'.$file);
}

it('names the demo call to action «Agenda una demo» everywhere, never «Pedir una demo» or «Pedir mi demo»', function (): void {
    $offenders = array_filter(landingSources(), fn (string $file): bool => preg_match('/Pedir (una|mi) demo/', (string) file_get_contents($file)) === 1);

    expect(array_map('basename', $offenders))->toBe([]);
});

it('answers in the FAQ that tools come in plans and that Compensación can be hired on its own, instead of promising a la carte', function (): void {
    $faq = landingSource('landing-faq.tsx');

    expect($faq)->toContain('Jornada')->toContain('Cumplimiento')->toContain('Integral')->toContain('Compensación')
        ->not->toContain('agregas las demás cuando quieras');
});

it('does not say the free trial twice in the demo section: the steps already do', function (): void {
    expect(landingSource('demo-section.tsx'))->not->toContain('La prueba gratis la vemos')
        ->and(landingSource('demo-next-steps.tsx'))->toContain('arrancas la prueba');
});
