<?php

declare(strict_types=1);

use App\Modules\Seating\Enums\RowState;
use App\Modules\Seating\Support\ComplianceRow;
use App\Modules\Seating\Support\RoutePages;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

mutates(RoutePages::class);

// Rediseño v2, boards V2-Ruta-Silla and V3-Inspeccion: each of the thirteen requirements in the words an inspector would
// use, and what is behind it for each state it can be in, one thing or several.

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    $this->center = new WorkCenterSummary('3f1c0d1e-0000-4000-8000-000000000001', 'Planta Querétaro', false, 'Ensamble');
});

/**
 * What the inspection page writes for the given keys, all in `$state` with `$count` behind them.
 *
 * @param  list<string>  $keys
 * @return array<string, array{title: string, detail: string|null}>
 */
function seatingWords(object $test, array $keys, RowState $state, int $count = 0): array
{
    $page = resolve(RoutePages::class)->inspection($test->tenant, $test->center, array_map(fn (string $key): ComplianceRow => new ComplianceRow($key, $state, $count), $keys));

    return collect([...$page['ready'], ...$page['missing']])->mapWithKeys(fn (array $row): array => [$row['numeral'] => ['title' => $row['title'], 'detail' => $row['detail']]])->all();
}

it('names each requirement in plain words, met and with nothing behind it', function (): void {
    expect(seatingWords($this, array_map(fn (int $number): string => "r{$number}", range(1, 13)), RowState::Met))->toBe([
        'R1' => ['title' => 'Los puestos de pie, con su Figura 1', 'detail' => null],
        'R4' => ['title' => 'Asientos en cada puesto', 'detail' => null],
        'R5' => ['title' => 'Un asiento de descanso donde el asiento va aparte', 'detail' => null],
        'R6' => ['title' => 'Los asientos fuera del puesto, señalizados', 'detail' => null],
        'R2' => ['title' => 'Cada persona que trabaja de pie, evaluada', 'detail' => null],
        'R3' => ['title' => 'Cada evaluación firmada, en un acta', 'detail' => null],
        'R8' => ['title' => 'Cada persona enterada de su resultado', 'detail' => null],
        'R9' => ['title' => 'Quien tiene malestares, canalizado', 'detail' => null],
        'R7' => ['title' => 'Una medida para cada riesgo medio o alto', 'detail' => null],
        'R11' => ['title' => 'Pausas acordadas donde el asiento va aparte', 'detail' => null],
        'R10' => ['title' => 'Reglamento interior firmado, depositado y repartido', 'detail' => null],
        'R12' => ['title' => 'Diagnóstico y programa de seguridad y salud', 'detail' => null],
        'R13' => ['title' => 'La Comisión asignada al centro', 'detail' => null],
    ]);
});

it('tells what is missing behind each requirement, for one thing and for several', function (int $count, array $details): void {
    $words = seatingWords($this, array_map(fn (int $number): string => "r{$number}", range(1, 13)), RowState::Missing, $count);

    expect(array_map(fn (array $word): ?string => $word['detail'], $words))->toBe($details);
})->with([
    'one' => [1, [
        'R1' => 'Declara el sector y registra los puestos de pie',
        'R4' => '1 puesto sin asiento',
        'R5' => '1 puesto sin asiento de descanso',
        'R6' => '1 asiento sin señalizar',
        'R2' => '1 persona sin evaluar',
        'R3' => '1 evaluación firmada sin acta',
        'R8' => '1 persona sin confirmar su resultado',
        'R9' => '1 persona con malestares sin canalizar',
        'R7' => '1 evaluación de riesgo medio o alto sin medida',
        'R11' => '1 puesto con asiento aparte sin acuerdo de pausas',
        'R10' => 'Falta completarlo y firmarlo',
        'R12' => 'Falta el documento; hay plantilla con lo que ya capturaste',
        'R13' => 'Nadie de la Comisión está asignado al centro',
    ]],
    'several' => [3, [
        'R1' => 'Declara el sector y registra los puestos de pie',
        'R4' => '3 puestos sin asiento',
        'R5' => '3 puestos sin asiento de descanso',
        'R6' => '3 asientos sin señalizar',
        'R2' => '3 personas sin evaluar',
        'R3' => '3 evaluaciones firmadas sin acta',
        'R8' => '3 personas sin confirmar su resultado',
        'R9' => '3 personas con malestares sin canalizar',
        'R7' => '3 evaluaciones de riesgo medio o alto sin medida',
        'R11' => '3 puestos con asiento aparte sin acuerdo de pausas',
        'R10' => 'Falta completarlo y firmarlo',
        'R12' => 'Falta el documento; hay plantilla con lo que ya capturaste',
        'R13' => 'Nadie de la Comisión está asignado al centro',
    ]],
]);

it('tells what needs attention behind a requirement with a warning, and nothing where it has none', function (int $count, array $details): void {
    $words = seatingWords($this, array_map(fn (int $number): string => "r{$number}", range(1, 13)), RowState::Warning, $count);

    expect(array_map(fn (array $word): ?string => $word['detail'], $words))->toBe($details);
})->with([
    'one' => [1, [
        'R1' => '1 puesto sin nadie asignado',
        'R4' => '1 puesto con asientos de menos o por reparar',
        'R5' => '',
        'R6' => '',
        'R2' => '1 evaluación por renovar',
        'R3' => '',
        'R8' => '',
        'R9' => '',
        'R7' => '1 medida vencida',
        'R11' => '',
        'R10' => 'Falta depositarlo o repartirlo',
        'R12' => '',
        'R13' => '',
    ]],
    'several' => [3, [
        'R1' => '3 puestos sin nadie asignado',
        'R4' => '3 puestos con asientos de menos o por reparar',
        'R5' => '',
        'R6' => '',
        'R2' => '3 evaluaciones por renovar',
        'R3' => '',
        'R8' => '',
        'R9' => '',
        'R7' => '3 medidas vencidas',
        'R11' => '',
        'R10' => 'Falta depositarlo o repartirlo',
        'R12' => '',
        'R13' => '',
    ]],
]);

it('tells what is still to register behind a requirement with nothing to judge it by', function (): void {
    $words = seatingWords($this, ['r3', 'r5', 'r6', 'r7', 'r8', 'r9', 'r11'], RowState::Pending);

    expect(array_map(fn (array $word): ?string => $word['detail'], $words))->toBe([
        'R5' => 'Aún no hay puestos clasificados',
        'R6' => 'Aún no hay asientos registrados',
        'R3' => 'Aún no hay evaluaciones firmadas',
        'R8' => 'Aún no hay evaluaciones firmadas',
        'R9' => 'Aún no hay evaluaciones firmadas',
        'R7' => 'Aún no hay evaluaciones firmadas',
        'R11' => 'Aún no hay puestos clasificados',
    ]);
});
