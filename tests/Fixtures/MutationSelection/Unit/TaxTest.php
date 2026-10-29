<?php

declare(strict_types=1);

use App\Billing\Tax;

// Tax has no source file: a class the diff deleted weighs nothing.
mutates(Tax::class);
