<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        // Sail's Vite service keeps public/hot in this checkout; tests render the build, never the live dev server.
        Vite::useHotFile(storage_path('framework/testing/vite-never-hot'));
    }

    /** The tests that need a Fortify feature fail when the app turns it off: a skip would hide the lost feature. */
    protected function assertFortifyHas(string $feature): void
    {
        $this->assertTrue(Features::enabled($feature), "Fortify feature [{$feature}] is off in config/fortify.php.");
    }
}
