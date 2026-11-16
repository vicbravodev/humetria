<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Providers;

use App\Modules\WorkerAccess\Contracts\WorkerCredentials;
use App\Modules\WorkerAccess\Contracts\WorkerKeys;
use App\Modules\WorkerAccess\Contracts\WorkerLinks;
use App\Modules\WorkerAccess\Contracts\WorkerSession;
use App\Modules\WorkerAccess\Contracts\WorkerSignatures;
use App\Modules\WorkerAccess\Support\CeremonyKeys;
use App\Modules\WorkerAccess\Support\CeremonySignatures;
use App\Modules\WorkerAccess\Support\CompanyWorkerCredentials;
use App\Modules\WorkerAccess\Support\RouteWorkerLinks;
use App\Modules\WorkerAccess\Support\WorkerSessionStore;
use Illuminate\Auth\GenericUser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * WorkerAccess: the identity of the person who works. Its contracts are bound here as they appear.
 */
final class WorkerAccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WorkerSignatures::class, CeremonySignatures::class);
        $this->app->bind(WorkerKeys::class, CeremonyKeys::class);
        $this->app->bind(WorkerCredentials::class, CompanyWorkerCredentials::class);
        $this->app->bind(WorkerSession::class, WorkerSessionStore::class);
        $this->app->bind(WorkerLinks::class, RouteWorkerLinks::class);
    }

    public function boot(): void
    {
        // The `worker` guard (config/auth.php): the person of the session that holds in this request, or nobody.
        Auth::viaRequest('worker-session', function (): ?GenericUser {
            $person = $this->app->make(WorkerSessionStore::class)->current();

            return $person === null ? null : new GenericUser(['id' => $person->employeeId, 'name' => $person->personName]);
        });

        // Entering takes three requests (the page, the options, the signature). Per address 300 a minute, as marking (a shift
        // behind one router); per key 12 a minute, whatever the address. Only the key id's hash names its counter.
        RateLimiter::for('worker-enter', function (Request $request): array {
            $key = $request->input('credential.id');

            return [
                Limit::perMinute(300)->by('worker-enter-ip:'.$request->ip()),
                ...(is_string($key) ? [Limit::perMinute(12)->by('worker-enter-key:'.hash('sha256', $key))] : []),
            ];
        });

        // Asking for a link: per address 10 a minute; per email 5 an hour, whatever the address. Only the hash of the email
        // (as it is compared: trimmed and in lower case) names its counter.
        RateLimiter::for('worker-link-request', function (Request $request): array {
            $email = $request->input('email');

            return [
                Limit::perMinute(10)->by('worker-link-request-ip:'.$request->ip()),
                ...(is_string($email) ? [Limit::perHour(5)->by('worker-link-request-email:'.hash('sha256', mb_strtolower(trim($email))))] : []),
            ];
        });

        // Registering a key takes three requests (the page, the options, the key): per address.
        RateLimiter::for('worker-key', fn (Request $request): Limit => Limit::perMinute(30)->by('worker-key-ip:'.$request->ip()));

        // Opening a link takes two requests (the page, «Continuar»): per address, and per link, so guessing or replaying one is
        // slowed down even from many addresses. Only the link's hash names its counter.
        RateLimiter::for('worker-link', fn (Request $request): array => [
            Limit::perMinute(30)->by('worker-link-ip:'.$request->ip()),
            Limit::perMinute(10)->by('worker-link-token:'.hash('sha256', is_string($token = $request->route('token')) ? $token : '')),
        ]);
    }
}
