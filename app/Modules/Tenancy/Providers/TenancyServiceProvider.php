<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Providers;

use App\Models\User;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Support\CompanyDirectory;
use App\Modules\Tenancy\Support\CompanyMembers;
use App\Modules\Tenancy\Support\CompanyWorkCenters;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Auth\Access\Response as AccessResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ResolveTenant fills it in, everyone else reads it through the contract.
        $this->app->bind(CurrentTenant::class, TenantContext::class);
        $this->app->bind(WorkCenters::class, CompanyWorkCenters::class);
        // One instance per request, and the contract points at it, so a role change can drop its memo.
        $this->app->scoped(CompanyMembers::class);
        $this->app->scoped(Members::class, fn (Application $app): CompanyMembers => $app->make(CompanyMembers::class));
        $this->app->bind(Companies::class, CompanyDirectory::class);
    }

    public function boot(): void
    {
        // Only the superadmin manages companies, through Gate::before; everyone else gets a 404 that reveals nothing.
        Gate::define('manage-tenants', fn (User $user): AccessResponse => AccessResponse::denyAsNotFound());

        // The invitation link is public. Viewing it has its own budget, so the page shown again after a mistyped
        // password does not spend the attempts.
        RateLimiter::for('invitation-views', fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));

        // Each link gets ten attempts a minute from an address, and the address sixty across links. The path carries
        // the token; the throttle stores only a hash of the key.
        RateLimiter::for('invitation-attempts', fn (Request $request): array => [
            Limit::perMinute(10)->by('link:'.$request->path().'|'.$request->ip()),
            Limit::perMinute(60)->by('address:'.$request->ip()),
        ]);
    }
}
