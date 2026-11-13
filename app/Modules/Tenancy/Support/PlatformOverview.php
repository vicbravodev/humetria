<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Billing\Contracts\PlatformRevenue;
use App\Modules\Demos\Contracts\DemoLeads;
use App\Modules\Tenancy\Actions\ListTenants;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Query\Builder;

/**
 * The platform in a few figures, for its superadmin: companies by status, the income they contract, demo requests by status and
 * how many companies nobody has entered lately. Each figure is one query however much the platform holds.
 */
final readonly class PlatformOverview
{
    /** A company nobody has entered for this many days is quiet. */
    private const int QUIET_DAYS = 30;

    public function __construct(private ListTenants $tenants, private PlatformRevenue $revenue, private DemoLeads $demos) {}

    /**
     * @return array{companies: array<string, int>, trialsEndingSoon: int, revenueCents: int, demos: array<string, int>, inactive: int}
     */
    public function handle(): array
    {
        $summary = $this->tenants->summary('');

        return [
            'companies' => $summary['counts'],
            'trialsEndingSoon' => $summary['trialsEndingSoon'],
            'revenueCents' => $this->revenue->monthlyContractedCents(),
            'demos' => $this->demos->countsByStatus(),
            'inactive' => $this->inactive(),
        ];
    }

    /** Companies still in use (not archived, and started) where nobody who belongs to them has signed in for 30 days, or ever. */
    private function inactive(): int
    {
        return Tenant::query()
            ->where(fn ($query) => $query->whereNull('restriction')->orWhere('restriction', '<>', 'archived'))
            // One that has not started (no trial, no term) was never open to anyone: it is not one nobody enters.
            ->where(fn ($query) => $query->whereNotNull('restriction')->orWhereNotNull('active_until')->orWhereNotNull('trial_started_at'))
            ->whereNotExists(fn (Builder $members) => $members
                ->from('tenant_memberships')
                ->join('users', 'users.id', '=', 'tenant_memberships.user_id')
                ->whereColumn('tenant_memberships.tenant_id', 'tenants.id')
                ->where('users.last_login_at', '>=', now()->subDays(self::QUIET_DAYS)))
            ->count();
    }
}
