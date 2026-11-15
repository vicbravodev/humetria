<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Geolocation;
use App\Http\Impersonation;
use App\Http\SsrPages;
use App\Logging\PostHogLogs;
use App\Logging\RedactEveryChannel;
use App\Models\User;
use App\Support\DbIpGeolocation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Ssr\SsrState;
use Laravel\Horizon\Events\LongWaitDetected;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Geolocation::class, DbIpGeolocation::class);

        // Before any channel is built: whatever channel a log goes to, sensitive keys are redacted.
        foreach (array_keys(Config::array('logging.channels')) as $channel) {
            Config::push("logging.channels.{$channel}.tap", RedactEveryChannel::class);
        }

        // Who needs a clinical assessment after a Guía I is health data of named people: never for a superadmin acting
        // as the owner. Hooked here, in register, so it runs before the check Spatie adds when it boots: a permission
        // the owner holds would otherwise answer first.
        $this->app->afterResolving(GateContract::class, fn (GateContract $gate) => $gate->before(
            fn (User $user, string $ability): ?bool => $ability === 'view-trauma-screenings' && $this->app->make(Impersonation::class)->isActive() ? false : null,
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // PostHog Logs leave in one batch when a request or command ends, and after each job of a worker that never ends.
        $this->app->terminating(PostHogLogs::flush(...));
        Event::listen([JobProcessed::class, JobExceptionOccurred::class], PostHogLogs::flush(...));
        $this->logOperationalFailures();

        // Spatie's super-admin pattern: null lets every other user fall through to the real ability.
        Gate::before(fn (User $user): ?bool => $user->isSuperadmin() ? true : null);

        // Node renders only what SsrPages names, and only for a visitor. Evaluated when the page is about to be sent to
        // Node, with the page already in SsrState; with a closure registered the package no longer reads
        // inertia.ssr.enabled, so SsrPages reads it.
        Inertia::disableSsr(fn (): bool => ! SsrPages::allows(request(), app(SsrState::class)->page));
    }

    /**
     * The lines PostHog's alerts read (docs/operations/observability.md), one place for every job and every scheduled
     * command: names, counts and classes, never a payload or an error's words.
     */
    private function logOperationalFailures(): void
    {
        Event::listen(fn (JobFailed $event) => Log::error('queue.job_failed', [
            'job' => $event->job->resolveName(),
            'queue' => $event->job->getQueue(),
            'connection' => $event->connectionName,
            'attempts' => $event->job->attempts(),
            'exception' => $event->exception::class,
        ]));

        Event::listen(fn (LongWaitDetected $event) => Log::warning('queue.long_wait', [
            'connection' => $event->connection,
            'queue' => $event->queue,
            'seconds' => $event->seconds,
        ]));

        Event::listen(fn (ScheduledTaskFailed $event) => Log::error('schedule.command_failed', [
            'command' => Str::after($event->task->command ?? $event->task->description ?? '', "'artisan' "),
            'exception' => $event->exception::class,
            'runtime' => $event->runtime,
        ]));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::shouldBeStrict(! app()->isProduction());

        URL::forceHttps(app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
