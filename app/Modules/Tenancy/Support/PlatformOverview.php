<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Billing\Contracts\PlatformCost;
use App\Modules\Billing\Contracts\PlatformRevenue;
use App\Modules\Billing\Contracts\Receivables;
use App\Modules\Billing\Data\OverdueAccount;
use App\Modules\Demos\Contracts\DemoLeads;
use App\Modules\Messaging\Contracts\PlatformDeliveries;
use App\Modules\Messaging\Data\ChannelTraffic;
use App\Modules\Tenancy\Actions\ListTenants;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

/**
 * The platform in a few figures, for its superadmin: companies by status, the income they contract, demo requests by status and
 * how many companies nobody has entered lately. Each figure is one query however much the platform holds.
 */
final readonly class PlatformOverview
{
    /** «Hoy» names this many accounts past due; the rest are behind «Ver todo». */
    private const int OVERDUE_SHOWN = 3;

    /** A company nobody has entered for this many days is quiet. */
    private const int QUIET_DAYS = 30;

    public function __construct(private ListTenants $tenants, private PlatformRevenue $revenue, private PlatformCost $cost, private DemoLeads $demos, private Receivables $receivables, private PlatformDeliveries $deliveries) {}

    /**
     * @return array{companies: array<string, int>, trialsEndingSoon: int, revenueCents: int, demos: array<string, int>, inactive: int, whatsApp: array{messages: int, costCents: int, chargedCents: int, marginCents: int}, overdue: array{cents: int, accounts: list<array{id: string, name: string, cents: int, days: int}>}, demosWaiting: array{new: int, unattended: int}, rejectedToday: int}
     */
    public function handle(): array
    {
        $summary = $this->tenants->summary('');
        $whatsApp = $this->cost->whatsAppMonth(CarbonImmutable::now());
        $waiting = $this->demos->waiting();

        return [
            'companies' => $summary['counts'],
            'trialsEndingSoon' => $summary['trialsEndingSoon'],
            'revenueCents' => $this->revenue->monthlyContractedCents(),
            'demos' => $this->demos->countsByStatus(),
            'inactive' => $this->inactive(),
            // The month so far: what Meta charged for the WhatsApp that went out, what the companies were charged, and the difference.
            'whatsApp' => ['messages' => $whatsApp->messages, 'costCents' => $whatsApp->costCents, 'chargedCents' => $whatsApp->chargedCents, 'marginCents' => $whatsApp->marginCents()],
            // What needs the superadmin today: money past due, demo requests waiting, messages that did not go out.
            'overdue' => [
                'cents' => $this->receivables->overdueCents(),
                'accounts' => array_map(fn (OverdueAccount $account): array => [
                    'id' => $account->accountId,
                    'name' => $account->legalName,
                    'cents' => $account->overdueCents,
                    'days' => $account->daysPastDue,
                ], $this->receivables->overdueAccounts(self::OVERDUE_SHOWN)),
            ],
            'demosWaiting' => ['new' => $waiting->new, 'unattended' => $waiting->unattended],
            'rejectedToday' => $this->rejectedToday(),
        ];
    }

    /** The messages rejected today, the day as Mexico City counts it, over every channel. */
    private function rejectedToday(): int
    {
        $from = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay()->utc();

        return array_sum(array_map(fn (ChannelTraffic $channel): int => $channel->rejected, $this->deliveries->byChannel($from, $from->addDay())));
    }

    /** Companies still in use (not archived, and started) where nobody who belongs to them has signed in for 30 days, or ever. */
    private function inactive(): int
    {
        return Tenant::query()
            ->where('is_demo', false)
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
