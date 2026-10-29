<?php

declare(strict_types=1);

use App\Modules\Shop\Actions\PlaceOrder;
use App\Modules\Shop\Models\Order;

mutates(PlaceOrder::class, Order::class);
