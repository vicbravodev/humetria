<?php

declare(strict_types=1);

use App\Modules\Shop\Http\Controllers\OrderController;

// A controller is too thin to mutate: this file never joins a module's run.
mutates(OrderController::class);
