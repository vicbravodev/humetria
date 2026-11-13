<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Providers;

use App\Models\User;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Support\CompanyDirectory;
use App\Modules\Tenancy\Support\CompanyLimitCounters;
use App\Modules\Tenancy\Support\CompanyMembers;
use App\Modules\Tenancy\Support\CompanyModuleAccess;
use App\Modules\Tenancy\Support\CompanyWorkCenters;
use App\Modules\Tenancy\Support\ModuleStates;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Tenancy\Support\TenantRegistry;
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
        $this->app->bind(CompanyRegistry::class, TenantRegistry::class);
        // One instance per request, so the modules of a company are read once; whoever changes them drops the memo.
        $this->app->scoped(ModuleStates::class);
        $this->app->bind(CompanyModules::class, CompanyModuleAccess::class);
        $this->app->bind(CompanyLimits::class, CompanyLimitCounters::class);
    }

    public function boot(): void
    {
        // Only the superadmin manages companies, through Gate::before; everyone else gets a 404 that reveals nothing.
        Gate::define('manage-tenants', fn (User $user): AccessResponse => AccessResponse::denyAsNotFound());

        // Looking at the console, and entering a company as its owner, is for the support role too (the superadmin passes
        // through Gate::before): it changes nothing, so every change stays behind manage-tenants.
        Gate::define('view-console', fn (User $user): AccessResponse => $user->isSupport() ? AccessResponse::allow() : AccessResponse::denyAsNotFound());

        // The audit trail and the platform's users are the superadmin's alone (Gate::before lets them through): support
        // is told it may not, since it does see the console around them.
        Gate::define('view-audit', fn (User $user): AccessResponse => AccessResponse::deny());
        Gate::define('manage-platform-users', fn (User $user): AccessResponse => AccessResponse::deny());
        Gate::define('view-platform-overview', fn (User $user): AccessResponse => AccessResponse::deny());

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
