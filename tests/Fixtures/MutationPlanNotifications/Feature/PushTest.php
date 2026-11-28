<?php

declare(strict_types=1);

use App\Modules\Notifications\Actions\ForgetPhones;
use App\Modules\Notifications\Notifications\PushDigest;

// A module called Notifications is a module like any other: only its own notifications are thin.
mutates(ForgetPhones::class, PushDigest::class);
