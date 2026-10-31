<?php

declare(strict_types=1);

use App\Modules\Instruments\Contracts\Nom037Checklist;
use App\Modules\Instruments\Data\ChecklistItem;
use App\Modules\Instruments\Data\Favorable;
use App\Modules\Instruments\Data\Requirement;
use App\Modules\Instruments\Support\Nom037\BaseChecklist;

mutates(BaseChecklist::class);

/** @return list<ChecklistItem> */
function checklist(): array
{
    return app(Nom037Checklist::class)->items();
}

/** @return array<string, array{string, Favorable}> */
function favorableAnnex(): array
{
    preg_match_all('/^\| `(S[12]-\d\d)` \|.*\| (Sí|No|Informativa) \|$/m', (string) file_get_contents(dirname(__DIR__, 3).'/docs/normas/nom-037/criterios.md'), $m, PREG_SET_ORDER);

    $annex = [];
    foreach ($m as $row) {
        $annex[$row[1]] = [$row[1], match ($row[2]) {
            'Sí' => Favorable::Yes,
            'No' => Favorable::No,
            default => Favorable::Informative,
        }];
    }

    return $annex;
}

it('transcribes the 109 Apéndice 3 questions literally, in order and by section', function (): void {
    preg_match_all('/^- `(S([12])-\d\d)` (.+)$/m', (string) file_get_contents(base_path('docs/normas/nom-037/texto-de-referencia.md')), $m);
    $dof = array_values(array_filter(checklist(), fn (ChecklistItem $i): bool => $i->fromDof));

    expect($m[1])->toHaveCount(109)
        ->and(array_map(fn (ChecklistItem $i): string => $i->code, $dof))->toBe($m[1])
        ->and(array_map(fn (ChecklistItem $i): int => $i->section, $dof))->toBe(array_map('intval', $m[2]))
        ->and(array_map(fn (ChecklistItem $i): string => $i->text, $dof))->toBe(array_map('trim', $m[3]));
});

it('puts every DOF question under the heading the DOF gives it', function (): void {
    $heading = '';
    $expected = [];
    foreach (explode("\n", (string) file_get_contents(base_path('docs/normas/nom-037/texto-de-referencia.md'))) as $line) {
        if (preg_match('/^\*\*(.+)\*\*$/', $line, $h) === 1) {
            $heading = $h[1];
        } elseif (preg_match('/^- `(S[12]-\d\d)` /', $line, $q) === 1) {
            $expected[$q[1]] = $heading;
        }
    }
    $actual = [];
    foreach (checklist() as $item) {
        if ($item->fromDof) {
            $actual[$item->code] = $item->heading;
        }
    }

    expect($actual)->toBe($expected);
});

it('uses the approved favorable answer of every DOF question', function (string $code, Favorable $favorable): void {
    $item = collect(checklist())->firstWhere('code', $code);

    expect($item)->toBeInstanceOf(ChecklistItem::class)->and($item->favorable)->toBe($favorable);
})->with(fn (): array => favorableAnnex());

it('has an approved favorable answer for each of the 109 DOF questions', function (): void {
    expect(favorableAnnex())->toHaveCount(109);
});

it('adds 13 Humetría questions, H1-01..H1-08 and H2-01..H2-05, each with a requirement', function (): void {
    $added = array_values(array_filter(checklist(), fn (ChecklistItem $i): bool => ! $i->fromDof));

    expect(array_map(fn (ChecklistItem $i): string => $i->code, $added))->toBe([
        ...array_map(fn (int $n): string => sprintf('H1-%02d', $n), range(1, 8)),
        ...array_map(fn (int $n): string => sprintf('H2-%02d', $n), range(1, 5)),
    ])
        ->and(array_filter($added, fn (ChecklistItem $i): bool => $i->requirement === null))->toBe([])
        ->and(collect($added)->where('favorable', Favorable::Informative)->pluck('code')->all())->toBe(['H1-04']);
});

it('covers every requirement in both sections', function (int $section): void {
    $covered = collect(checklist())->where('section', $section)->pluck('requirement')->filter()->unique();

    expect($covered)->toHaveCount(count(Requirement::cases()));
})->with([1, 2]);

it('gives no requirement to informative questions', function (): void {
    $withRequirement = collect(checklist())->where('favorable', Favorable::Informative)->whereNotNull('requirement')->pluck('code')->all();

    expect($withRequirement)->toBe(['H1-04']);
});

it('marks S2-61 as the only sensitive base question', function (): void {
    expect(collect(checklist())->where('sensitive', true)->pluck('code')->all())->toBe(['S2-61']);
});

it('gives every code once, 122 in total, under version dof-2023-06-08', function (): void {
    $codes = array_map(fn (ChecklistItem $i): string => $i->code, checklist());

    expect($codes)->toHaveCount(122)
        ->and(array_unique($codes))->toHaveCount(122)
        ->and(app(Nom037Checklist::class)->version())->toBe('dof-2023-06-08');
});

it('words every requirement label in Spanish', function (): void {
    foreach (Requirement::cases() as $case) {
        expect($case->label())->not->toBe('')->and($case->label())->not->toBe($case->value);
    }
});

it('hands over the literal usage instructions, the 5.5 notice and the base consent', function (): void {
    $checklist = app(Nom037Checklist::class);
    $reference = (string) file_get_contents(base_path('docs/normas/nom-037/texto-de-referencia.md'));
    preg_match('/^1\.- .+?(?=\n\n### Sección I)/ms', $reference, $m);

    expect($checklist->instructions())->toBe(trim($m[0]))
        ->and($checklist->responsibilityNotice())->toContain('no significa el traslado de la responsabilidad')
        ->and($checklist->baseConsent())->toContain('La empresa es la responsable')->toContain('fotograf')->toContain('salud');
});
