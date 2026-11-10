<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\TenureBand;
use Carbon\CarbonImmutable;

mutates(TenureBand::class);

it('counts the years completed, not started', function (string $start, string $day, TenureBand $expected): void {
    expect(TenureBand::on(new CarbonImmutable($start), new CarbonImmutable($day)))->toBe($expected);
})->with([
    'a day short of one year' => ['2025-11-10', '2026-11-09', TenureBand::UnderOne],
    'exactly one year' => ['2025-11-10', '2026-11-10', TenureBand::OneToThree],
    'a day short of three years' => ['2023-11-10', '2026-11-09', TenureBand::OneToThree],
    'exactly three years' => ['2023-11-10', '2026-11-10', TenureBand::ThreeToSix],
    'a day short of six years' => ['2020-11-10', '2026-11-09', TenureBand::ThreeToSix],
    'exactly six years' => ['2020-11-10', '2026-11-10', TenureBand::SixPlus],
    'twenty years' => ['2006-01-01', '2026-11-10', TenureBand::SixPlus],
    'the first day' => ['2026-11-10', '2026-11-10', TenureBand::UnderOne],
]);

it('is named in Spanish and keyed by a stable value', function (): void {
    expect(array_map(fn (TenureBand $band): string => $band->label(), TenureBand::cases()))
        ->toBe(['Menos de 1 año', 'De 1 a 3 años', 'De 3 a 6 años', '6 años o más'])
        ->and(array_map(fn (TenureBand $band): string => $band->value, TenureBand::cases()))
        ->toBe(['lt1', '1to3', '3to6', '6plus']);
});
