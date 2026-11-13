<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Billing\Contracts\CompanyPlans;
use App\Modules\Billing\Data\PlanSummary;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Data\ModuleRow;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;

/**
 * What the owner of a company reads about its plan: which one, how much of its limits it uses and which tools it has. Words and
 * counts, never money: prices, charges and balances are the staff's.
 */
final readonly class PlanPage
{
    public function __construct(private CompanyPlans $plans, private CompanyLimits $limits, private CompanyModules $modules, private RealUsage $usage) {}

    /**
     * @return array{plan: array{name: string, since: string, until: string}|null, usage: array{centers: array{used: int, limit: int|null}, people: array{used: int, limit: int|null}}, tools: list<array{label: string, active: bool, endsOn: string|null}>, support: array{email: string|null}}
     */
    public function handle(Tenant $tenant): array
    {
        $plan = $this->plans->current($tenant->id);

        return [
            'plan' => $plan === null ? null : $this->plan($plan),
            'usage' => [
                'centers' => $this->use($tenant->id, CompanyLimits::CENTERS),
                'people' => $this->use($tenant->id, CompanyLimits::SEATS),
            ],
            'tools' => array_map(fn (ModuleRow $row): array => ['label' => $row->label, 'active' => $row->enabled, 'endsOn' => $row->endsOn], array_values(array_filter($this->modules->list($tenant->id), fn (ModuleRow $row): bool => $row->existsInCode))),
            'support' => ['email' => $this->supportEmail()],
        ];
    }

    /**
     * @return array{name: string, since: string, until: string}
     */
    private function plan(PlanSummary $plan): array
    {
        return ['name' => $plan->name, 'since' => $this->day($plan->since), 'until' => $this->day($plan->renewsOn)];
    }

    /**
     * @return array{used: int, limit: int|null}
     */
    private function use(string $tenantId, string $resource): array
    {
        return ['used' => $this->usage->of($tenantId, $resource), 'limit' => $this->limits->limit($tenantId, $resource)?->amount];
    }

    /** A calendar day as the panel writes it ("1 dic 2026"), without moving it between time zones: it is a date, not a moment. */
    private function day(CarbonImmutable $day): string
    {
        return str_replace('.', '', $day->isoFormat('D MMM YYYY'));
    }

    private function supportEmail(): ?string
    {
        $email = config('app.support_email');

        return is_string($email) && $email !== '' ? $email : null;
    }
}
