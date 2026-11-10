<?php

declare(strict_types=1);

use App\Modules\Leave\Support\PeriodWindow;
use Carbon\CarbonImmutable;

// Here and not with the rest of PeriodWindow's tests (tests/Unit/Leave): the local day needs the app's configuration.
mutates(PeriodWindow::class);

it('takes today from the local day, as a plain date', function (string $now, string $today): void {
    $this->travelTo($now);

    expect(PeriodWindow::today()->equalTo(CarbonImmutable::parse($today)))->toBeTrue()
        ->and(PeriodWindow::today()->toDateTimeString())->toBe("{$today} 00:00:00");
})->with([
    // 05:30 UTC is 23:30 of the day before in Mexico City.
    'before local midnight' => ['2026-03-15 05:30:00', '2026-03-14'],
    'after local midnight' => ['2026-03-15 06:00:00', '2026-03-15'],
    'at noon' => ['2026-03-15 18:00:00', '2026-03-15'],
]);
