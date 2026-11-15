<?php

declare(strict_types=1);

namespace Tests;

use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
use Laravel\Fortify\Features;
use Tests\Support\SafeFakerNames;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        // Sail's Vite service keeps public/hot in this checkout; tests render the build, never the live dev server.
        Vite::useHotFile(storage_path('framework/testing/vite-never-hot'));

        // The factories draw names, companies and dates from Faker, which uses mt_rand. Seeded by the test's own name,
        // every run (local, CI, any shard or order) gets the same data: a random company called «Zapata» or «Pedroza»
        // that trips a privacy assertion fails every time, not one run in fifty.
        // TEST_SEED mixes another seed in, to hunt flaky data: `TEST_SEED=7 composer test:coverage` (see docs/testing.md).
        mt_srand(crc32(static::class.'::'.$this->nameWithDataSet().'::'.(getenv('TEST_SEED') ?: '')));

        // Random people never carry a name that a privacy assertion looks for in the same page (see SafeFakerNames).
        fake()->addProvider(new SafeFakerNames(fake()));

        // TEST_NOW starts each test's clock at that instant, then lets it run, to hunt flaky dates:
        // `TEST_NOW='2026-11-14 23:59:59' composer test:coverage`. Tests that fix their own time still win;
        // they only see this clock where they leave it alone. It is not frozen: two marks in a row differ, as in production.
        if (is_string($start = getenv('TEST_NOW')) && $start !== '') {
            $offset = (new DateTimeImmutable($start, new DateTimeZone('UTC')))->getTimestamp() - (new DateTimeImmutable('now'))->getTimestamp();
            Carbon::setTestNow(fn (): Carbon => Carbon::instance(new DateTimeImmutable("now {$offset} seconds")));
        }
    }

    /** The tests that need a Fortify feature fail when the app turns it off: a skip would hide the lost feature. */
    protected function assertFortifyHas(string $feature): void
    {
        $this->assertTrue(Features::enabled($feature), "Fortify feature [{$feature}] is off in config/fortify.php.");
    }
}
