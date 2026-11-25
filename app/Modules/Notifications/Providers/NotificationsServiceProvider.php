<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Providers;

use App\Modules\Attendance\Events\CorrectionResolved;
use App\Modules\Attendance\Events\OfflineMarkReviewed;
use App\Modules\Notifications\Console\GenerateVapidKeys;
use App\Modules\Notifications\Listeners\ForgetOnAccessRevoked;
use App\Modules\Notifications\Listeners\NotifyCorrectionResolved;
use App\Modules\Notifications\Listeners\NotifyMarkReviewed;
use App\Modules\WorkerAccess\Events\WorkerAccessRevoked;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Notifications: push notices with no content to the person who works. It reacts to the public events of Attendance and WorkerAccess.
 */
final class NotificationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OfflineMarkReviewed::class, NotifyMarkReviewed::class);
        Event::listen(CorrectionResolved::class, NotifyCorrectionResolved::class);
        Event::listen(WorkerAccessRevoked::class, ForgetOnAccessRevoked::class);

        if ($this->app->runningInConsole()) {
            $this->commands([GenerateVapidKeys::class]);
        }
    }
}
