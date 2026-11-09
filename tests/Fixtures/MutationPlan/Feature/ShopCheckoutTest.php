<?php

declare(strict_types=1);

use App\Billing\Invoice;
use App\Modules\Shop\Models\Order;

// One test file can exercise the classes of two modules: it runs in both.
mutates(Order::class, Invoice::class);
