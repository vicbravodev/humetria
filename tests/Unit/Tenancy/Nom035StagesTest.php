<?php

declare(strict_types=1);

use App\Modules\Tenancy\Data\RouteProgress;
use App\Modules\Tenancy\Support\Nom035Facts;
use App\Modules\Tenancy\Support\Nom035Stages;

mutates(Nom035Stages::class);

/** A center of 30 people evaluated within its cycle, with every stage done, but what is given. */
function nom035(mixed ...$changes): Nom035Facts
{
    return new Nom035Facts(...[
        'policy' => true,
        'standing' => 'up_to_date',
        'until' => '29 oct 2028',
        'running' => null,
        'draft' => false,
        'onlyGuideOne' => false,
        'toChannel' => 0,
        'shared' => true,
        'program' => null,
        'reported' => true,
        ...$changes,
    ]);
}

it('is up to date until the next evaluation is due when every stage is done', function (): void {
    expect(Nom035Stages::from(nom035()))->toEqual(new RouteProgress(true, [true, true, true, true, true, true], 'al día hasta el 29 oct 2028'));
});

it('starts with the policy of prevention', function (): void {
    expect(Nom035Stages::from(nom035(policy: false)))->toEqual(new RouteProgress(true, [false, true, true, true, true, true], 'define tu política de prevención'));
});

it('tells how the evaluation goes while it runs, and leaves what follows it for after', function (int $daysLeft, string $closes): void {
    expect(Nom035Stages::from(nom035(standing: 'in_course', running: ['answered' => 18, 'target' => 60, 'daysLeft' => $daysLeft])))
        ->toEqual(new RouteProgress(true, [true, false, false, false, false, false], "18 de 60 contestaron, {$closes}"));
})->with([
    [7, 'cierra en 7 días'],
    [1, 'cierra mañana'],
    [0, 'cierra hoy'],
]);

it('asks to evaluate a center never evaluated or overdue, or to launch its draft', function (): void {
    expect(Nom035Stages::from(nom035(standing: 'never', until: null))->status)->toBe('evalúa a tu personal')
        ->and(Nom035Stages::from(nom035(standing: 'never', until: null))->done)->toBe([true, false, false, false, false, false])
        ->and(Nom035Stages::from(nom035(standing: 'overdue', until: '2 nov 2026'))->status)->toBe('toca evaluar de nuevo: venció el 2 nov 2026')
        ->and(Nom035Stages::from(nom035(standing: 'never', until: null, draft: true))->status)->toBe('lanza la evaluación');
});

it('follows the evaluation with channeling, sharing the results, the Programa and the report, in that order', function (array $changes, int $stage, string $status): void {
    $done = [true, true, true, true, true, true];
    $done[$stage - 1] = false;

    expect(Nom035Stages::from(nom035(...$changes)))->toEqual(new RouteProgress(true, $done, $status));
})->with([
    'channeling' => [['toChannel' => 9], 3, 'canaliza a 9 personas'],
    'channeling one' => [['toChannel' => 1], 3, 'canaliza a 1 persona'],
    'sharing' => [['shared' => false], 4, 'comparte los resultados con tu equipo'],
    'no actions yet' => [['program' => 'none'], 5, 'arma el programa de intervención'],
    'actions running' => [['program' => 'in_progress'], 5, 'sigue tu programa de intervención'],
    'actions overdue' => [['program' => 'overdue'], 5, 'sigue tu programa de intervención'],
    'report' => [['reported' => false], 6, 'completa el informe 7.7'],
]);

it('takes a finished Programa, or none because nothing needed one, as done', function (): void {
    expect(Nom035Stages::from(nom035(program: 'done'))->done[4])->toBeTrue()
        ->and(Nom035Stages::from(nom035(program: null))->done[4])->toBeTrue();
});

it('asks a center of up to 15 people only for its policy, its Guía I and the channeling (num. 2 a)', function (): void {
    expect(Nom035Stages::from(nom035(onlyGuideOne: true, shared: false, program: 'none', reported: false)))->toEqual(new RouteProgress(true, [true, true, true, true, true, true], 'al día hasta el 29 oct 2028'));
});
