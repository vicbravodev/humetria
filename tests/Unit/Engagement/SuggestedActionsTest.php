<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\Bank\SuggestedActions;

mutates(SuggestedActions::class);

it('has two or three actions for each of the ten drivers', function (Driver $driver): void {
    expect(count(SuggestedActions::for($driver)))->toBeGreaterThanOrEqual(2)->toBeLessThanOrEqual(3);
})->with(Driver::cases());

it('has short actions that address the manager as tú and never repeat a question of the bank', function (Driver $driver): void {
    $questions = array_map(fn ($item): string => $item->text, ItemBank::all());

    foreach (SuggestedActions::for($driver) as $action) {
        expect(mb_strlen(trim($action)))->toBeGreaterThan(0)->toBeLessThanOrEqual(160)
            ->and(mb_strtolower($action))->not->toContain('usted')
            ->and($questions)->not->toContain($action);
    }
})->with(Driver::cases());

it('says the exact actions of the plan', function (): void {
    expect(SuggestedActions::for(Driver::Clarity))->toBe([
        'Presenta las 3 metas del año en una junta por área y deja que cada equipo escriba cómo aporta',
        'Revisa prioridades con tu equipo al inicio de cada mes',
    ])->and(SuggestedActions::for(Driver::Reward))->toHaveCount(3);
});
