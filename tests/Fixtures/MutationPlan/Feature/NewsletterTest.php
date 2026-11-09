<?php

declare(strict_types=1);

use App\Modules\Mail\Http\Requests\Subscribe;
use App\Modules\Mail\Notifications\Digest;

// Everything this module mutates is thin: the module has no run at all.
mutates(Subscribe::class, Digest::class);
