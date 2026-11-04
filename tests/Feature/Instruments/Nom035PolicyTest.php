<?php

declare(strict_types=1);

use App\Modules\Instruments\Contracts\Nom035Policy;
use App\Modules\Instruments\Data\PolicySection;
use App\Modules\Instruments\Support\Nom035\PolicyExample;

mutates(PolicyExample::class);

/**
 * The Guía de referencia IV as the annex (docs/normas/nom-035/texto-de-referencia.md) transcribes it, literal from the DOF.
 *
 * @return list<string>
 */
function annexPolicyLines(): array
{
    $annex = (string) file_get_contents(__DIR__.'/../../../docs/normas/nom-035/texto-de-referencia.md');
    $parts = preg_split('/^## /m', $annex) ?: [];
    $part = collect($parts)->first(fn (string $part): bool => preg_match('/^Guía de referencia IV\b/', $part) === 1);

    return array_values(array_filter(array_map(trim(...), explode("\n", (string) $part)), fn (string $line): bool => $line !== ''));
}

/** The text of the lines that start with `$prefix`, in order, with the prefix cut. @return list<string> */
function annexAfter(string $prefix): array
{
    $found = [];

    foreach (annexPolicyLines() as $line) {
        if (preg_match($prefix, $line, $match) === 1) {
            $found[] = substr($line, strlen($match[0]));
        }
    }

    return $found;
}

function policySection(string $key): PolicySection
{
    return collect(resolve(Nom035Policy::class)->template()->sections)->firstWhere('key', $key) ?? throw new RuntimeException("No section {$key}");
}

it('is the DOF edition the guide is transcribed from', function (): void {
    expect(resolve(Nom035Policy::class)->version())->toBe('dof-2018-10-23')
        ->and(resolve(Nom035Policy::class)->template()->version)->toBe('dof-2018-10-23');
});

it('has the nine commitments of the guide, literal, under their introduction', function (): void {
    $commitments = policySection('commitments');
    $introduction = annexAfter('/^En este centro de trabajo /');

    expect($commitments->items)->toBe(annexAfter('/^\d\. /'))
        ->and($commitments->items)->toHaveCount(9)
        ->and($commitments->listStyle)->toBe('numbers')
        ->and($commitments->text)->toBe('En este centro de trabajo {legal_name} '.substr($introduction[0], strlen('(Razón Social) ')))
        ->and(substr_count($commitments->text, '{'))->toBe(1);
});

it('has the eight principles of the guide, literal, the first with its eight letters', function (): void {
    $principles = annexAfter('/^\*\*Principio \d\.\*\* /');
    $first = policySection('principle_1');

    expect($principles)->toHaveCount(8)
        ->and($first->text)->toBe($principles[0])
        ->and($first->items)->toBe(annexAfter('/^- [a-h]\) /'))
        ->and($first->items)->toHaveCount(8)
        ->and($first->listStyle)->toBe('letters');

    foreach (range(2, 8) as $number) {
        $section = policySection("principle_{$number}");

        expect($section->text)->toBe($principles[$number - 1])
            ->and($section->items)->toBe([])
            ->and($section->heading)->toBe("Principio {$number}");
    }
});

it('introduces the principles with the sentence of the guide', function (): void {
    expect(policySection('principles')->text)->toBe('La política se basa en los principios siguientes:');
});

it('leaves the responsibles and the resources empty, required, with the note of the guide as help', function (): void {
    $template = resolve(Nom035Policy::class)->template();
    $note = annexAfter('/^Nota: /')[0];

    foreach (['responsibles', 'resources'] as $key) {
        expect(policySection($key)->text)->toBe('')
            ->and(policySection($key)->required)->toBeTrue()
            ->and(policySection($key)->removable)->toBeFalse();
    }

    expect($template->note)->toBe($note);
});

it('lets the principles go but never the commitments, the responsibles or the resources', function (): void {
    $sections = resolve(Nom035Policy::class)->template()->sections;

    expect(array_map(fn (PolicySection $section): string => $section->key, array_filter($sections, fn (PolicySection $section): bool => ! $section->removable)))
        ->toBe(['commitments', 'responsibles', 'resources'])
        ->and(array_map(fn (PolicySection $section): string => $section->key, $sections))
        ->toBe(['commitments', 'responsibles', 'resources', 'principles', ...array_map(fn (int $number): string => "principle_{$number}", range(1, 8))]);
});

it('has the company name as its only placeholder', function (): void {
    $text = collect(resolve(Nom035Policy::class)->template()->sections)
        ->flatMap(fn (PolicySection $section): array => [$section->heading, $section->text, ...$section->items])->implode("\n");

    expect(preg_match_all('/\{[a-z_]+\}/', $text, $found))->toBe(1)
        ->and($found[0])->toBe(['{legal_name}']);
});
