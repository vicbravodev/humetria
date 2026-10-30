<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Providers;

use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Instruments\Contracts\Nom035Guides;
use App\Modules\Instruments\Support\InstrumentCatalog;
use App\Modules\Instruments\Support\Nom035GuideRule;
use Illuminate\Support\ServiceProvider;

final class InstrumentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Nom035Guides::class, Nom035GuideRule::class);
        $this->app->bind(Instruments::class, InstrumentCatalog::class);
    }
}
