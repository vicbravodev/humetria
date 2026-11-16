<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Providers;

use App\Modules\WorkerAccess\Contracts\WorkerCredentials;
use App\Modules\WorkerAccess\Contracts\WorkerKeys;
use App\Modules\WorkerAccess\Contracts\WorkerSession;
use App\Modules\WorkerAccess\Contracts\WorkerSignatures;
use App\Modules\WorkerAccess\Support\CeremonyKeys;
use App\Modules\WorkerAccess\Support\CeremonySignatures;
use App\Modules\WorkerAccess\Support\CompanyWorkerCredentials;
use App\Modules\WorkerAccess\Support\WorkerSessionStore;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
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
    }

    public function boot(): void
    {
        // The `worker` guard (config/auth.php): the person of the session that holds in this request, or nobody.
        Auth::viaRequest('worker-session', function (): ?GenericUser {
            $person = $this->app->make(WorkerSessionStore::class)->current();

            return $person === null ? null : new GenericUser(['id' => $person->employeeId, 'name' => $person->personName]);
        });
    }
}
