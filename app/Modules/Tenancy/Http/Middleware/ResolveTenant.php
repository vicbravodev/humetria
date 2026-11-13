<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Assistant\Contracts\AiAccess;
use App\Modules\Tenancy\Actions\ListUserTenants;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Support\CompanyMembers;
use App\Modules\Tenancy\Support\CompanyRoles;
use App\Modules\Tenancy\Support\ModuleStates;
use App\Modules\Tenancy\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds a request to the company named in its URL. Only members get in: anyone else, a superadmin
 * included, gets the same 404 as for a company that does not exist, so the URL reveals nothing.
 * Membership is checked directly, not through a Policy, because Gate::before lets a superadmin pass.
 */
final class ResolveTenant
{
    private const int SWITCHER_COMPANIES = 10;

    public function __construct(
        private readonly ListUserTenants $listUserTenants,
        private readonly TenantContext $tenantContext,
        private readonly CompanyRoles $companyRoles,
        private readonly CompanyModules $modules,
        private readonly AiAccess $ai,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // A new request starts without the people of the last one's company (a long-lived process serves many).
        resolve(CompanyMembers::class)->forget();
        resolve(ModuleStates::class)->forget();
        $slug = $request->route('tenant');

        // Implicit route binding only runs when the action type-hints the model; this middleware must not depend on that.
        $tenant = is_string($slug) ? Tenant::query()->where('slug', $slug)->first() : $slug;

        $user = $request->user();

        abort_unless(
            $tenant instanceof Tenant
            && $user !== null
            && TenantMembership::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)->exists(),
            404,
        );

        $request->route()?->setParameter('tenant', $tenant);
        // Also tags every log line, audit entry and queued job with the company, and makes it Spatie's team.
        $this->tenantContext->set($tenant);
        // A company with no module rows has every module, and the page need not ask.
        resolve(ModuleStates::class)->prime($tenant);
        // Roles read before (as the superadmin's, with no team) must be read again inside the company.
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $role = $this->companyRoles->of($user, $tenant);

        // A membership without a role is broken data: nothing is shown rather than guessing.
        abort_if($role === null, 404);

        $status = $tenant->status();

        // Read once, though `can` and `permissions` both carry it.
        $humiAllowed = $user->can('use-assistant');
        $humiOn = null;
        $assistant = function () use ($humiAllowed, &$humiOn): bool {
            if (! $humiAllowed) {
                return false;
            }

            return $humiOn ??= $this->ai->enabled();
        };
        Inertia::share('tenant', [
            'slug' => $tenant->slug,
            'trade_name' => $tenant->trade_name,
            'status' => $status->value,
            'blocked' => $status->blocksPanel(),
            'read_only' => $status->isReadOnly(),
            'trial_days_left' => $tenant->trialDaysLeft(),
            'type' => $tenant->type->value,
            // What the company has, for the menu to hide the rest; the routes of the others answer 404 anyway.
            'modules' => $this->modules->enabledKeys($tenant->id),
            'role' => $role->value,
            'role_label' => $role->label(),
            // What the role allows; a read-only company has every writing one off, and `permissions` keeps the full set for the menu.
            'can' => $this->writable($permissions = [
                'manage_organization' => $user->can('manage-organization'),
                'manage_members' => $user->can('manage-members'),
                'manage_campaigns' => $user->can('manage-campaigns'),
                'view_trauma_screenings' => $user->can('view-trauma-screenings'),
                'manage_telework' => $user->can('manage-telework'),
                'review_workplaces' => $user->can('review-workplaces'),
                'view_seating' => $user->can('view-seating'),
                'manage_seating' => $user->can('manage-seating'),
                'assess_seating' => $user->can('assess-seating'),
                'view_compensation' => $user->can('view-compensation'),
                'manage_compensation' => $user->can('manage-compensation'),
                'view_interventions' => $user->can('view-interventions'),
                'manage_interventions' => $user->can('manage-interventions'),
                'view_disclosure' => $user->can('view-disclosure'),
                'manage_disclosure' => $user->can('manage-disclosure'),
                // Not offered where the company turned Humi off: the dock would only say it is unavailable. Asked when the props are
                // drawn, so a request that draws none (a JSON read) does not pay for it.
                'use_assistant' => $assistant,
                'manage_complaint_channel' => $user->can('manage-complaint-channel'),
                'handle_complaints' => $user->can('handle-complaints'),
                'view_attendance' => $user->can('view-attendance'),
                'manage_attendance' => $user->can('manage-attendance'),
                'view_org_chart' => $user->can('view-org-chart'),
                'manage_org_chart' => $user->can('manage-org-chart'),
                'view_vacations' => $user->can('view-vacations'),
                'manage_vacations' => $user->can('manage-vacations'),
                'view_climate' => $user->can('view-climate'),
                'manage_climate' => $user->can('manage-climate'),
                'view_pulse' => $user->can('view-pulse'),
                'manage_pulse' => $user->can('manage-pulse'),
            ], $status->isReadOnly()),
            'permissions' => $permissions,
        ]);
        // For the company switcher: the first few, and how many there are to see them all on their own page.
        Inertia::share('tenants', function () use ($user): array {
            $companies = $this->listUserTenants->handle($user->id, self::SWITCHER_COMPANIES);

            return [
                'data' => array_values($companies->getCollection()->map(fn (Tenant $company): array => ['slug' => $company->slug, 'trade_name' => $company->trade_name])->all()),
                'total' => $companies->total(),
            ];
        });

        return $next($request);
    }

    /**
     * A read-only company changes nothing, so the buttons that would change something go away with it: every permission that
     * writes is off, and the ones that read stay as they are. The server answers 423 to a write anyway.
     *
     * @param  array<string, bool|Closure(): bool>  $can
     * @return array<string, bool|Closure(): bool>
     */
    private function writable(array $can, bool $readOnly): array
    {
        if (! $readOnly) {
            return $can;
        }

        foreach ($can as $key => $allowed) {
            if (str_starts_with($key, 'manage_') || in_array($key, ['assess_seating', 'review_workplaces', 'handle_complaints'], true)) {
                $can[$key] = false;
            }
        }

        return $can;
    }
}
