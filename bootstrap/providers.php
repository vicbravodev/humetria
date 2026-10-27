<?php

declare(strict_types=1);

use App\Modules\Demos\Providers\DemosServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    DemosServiceProvider::class,
];
