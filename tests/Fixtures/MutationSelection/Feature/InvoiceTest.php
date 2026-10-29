<?php

declare(strict_types=1);

use App\Billing\Invoice as BillingInvoice;
use App\Modules\Shop\Models\Order;

mutates(BillingInvoice::class);
mutates(Order::class);
