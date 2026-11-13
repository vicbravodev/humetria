<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Exceptions\InvalidStatement;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\CsvCell;
use App\Modules\Billing\Support\Eligibility;
use App\Modules\Billing\Support\StatementBalances;
use App\Modules\Billing\Support\SubscriptionQuoter;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * The CSV of one of the three reports of the console (portfolio, balances, renewals), UTF-8 with a byte-order mark so a spreadsheet
 * opens its accents, every cell safe against formulas. Reading it is sensitive (who pays what): it is logged and audited.
 * ponytail: built in memory; stream by cursor if the portfolio ever reaches many thousands of companies.
 */
final readonly class ExportReport
{
    public const array REPORTS = ['portfolio', 'balances', 'renewals'];

    public function __construct(
        private RecordAudit $recordAudit,
        private CompanyRegistry $companies,
        private Eligibility $eligibility,
        private SubscriptionQuoter $quoter,
        private BuildStatement $build,
        private StatementBalances $balances,
    ) {}

    public function handle(string $report): string
    {
        $table = match ($report) {
            'portfolio' => $this->portfolio(),
            'balances' => $this->balances(),
            'renewals' => $this->renewals(),
            default => throw new InvalidArgumentException("Unknown report {$report}"),
        };

        Log::info('billing.report_exported', ['report' => $report, 'rows' => count($table) - 1]);
        $this->recordAudit->handle('billing.report_exported', null, ['report' => $report, 'rows' => count($table) - 1]);

        return "\xEF\xBB\xBF".implode('', array_map($this->line(...), $table));
    }

    /** @return list<list<string>> */
    private function portfolio(): array
    {
        $rows = [['Cuenta', 'RFC de la cuenta', 'Tipo', 'Empresa', 'RFC de la empresa', 'Plan', 'Perfil', 'Estado', 'Personas', 'Centros', 'Importe mensual sin IVA', 'Próxima renovación']];
        $links = AccountCompany::query()->current()->orderBy('billing_account_id')->orderBy('starts_on')->orderBy('id')->get(['billing_account_id', 'tenant_id']);
        $accounts = BillingAccount::query()->whereIn('id', $links->pluck('billing_account_id'))->get()->keyBy('id');
        $briefs = $this->companies->many(array_values(array_map(fn (AccountCompany $link): string => $link->tenant_id, $links->all())));
        $subscriptions = Subscription::query()->whereIn('tenant_id', array_keys($briefs))->get()->keyBy('tenant_id');
        $eligible = [];
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();

        foreach ($links as $link) {
            $account = $accounts->get($link->billing_account_id);
            $brief = $briefs[$link->tenant_id] ?? null;

            if ($account === null || $brief === null) {
                continue;
            }

            $subscription = $subscriptions->get($link->tenant_id);
            $eligible[$account->id] ??= $this->eligibility->count($account);
            $subtotal = $subscription === null ? null : $this->quoter->quote($subscription, $eligible[$account->id], $today)->subtotalCents;

            $rows[] = [
                $account->legal_name, $account->rfc, $account->kind->label(), $brief->tradeName, $brief->rfc,
                $subscription?->plan->label() ?? 'Sin plan', $subscription?->profile->label() ?? '', $subscription?->status->label() ?? '',
                (string) ($subscription->seats ?? ''), (string) ($subscription?->allowedCenters() ?? ''),
                $subtotal === null ? '' : $this->pesos($subtotal), $subscription?->renews_on->toDateString() ?? '',
            ];
        }

        return $rows;
    }

    /** @return list<list<string>> */
    private function balances(): array
    {
        $rows = [['Cuenta', 'RFC', 'Estados con saldo', 'Saldo por cobrar', 'Saldo vencido', 'Emitido el más antiguo']];
        $open = Statement::query()->withBalance()->orderBy('issued_at')->orderBy('id')->get(['id', 'billing_account_id', 'total_cents', 'issued_at']);
        $paid = $this->balances->paid(array_values($open->map(fn (Statement $statement): string => $statement->id)->all()));
        $accounts = BillingAccount::query()->whereIn('id', $open->pluck('billing_account_id'))->orderBy('legal_name')->orderBy('id')->get();
        $cutoff = CarbonImmutable::now()->subDays(config()->integer('billing.overdue_after_days'));

        foreach ($accounts as $account) {
            $mine = $open->filter(fn (Statement $statement): bool => $statement->billing_account_id === $account->id);
            $oldest = $mine->first();

            $rows[] = [
                $account->legal_name, $account->rfc, (string) $mine->count(), $this->pesos($this->owed($mine, $paid)),
                $this->pesos($this->owed($mine->filter(fn (Statement $statement): bool => $statement->issued_at !== null && $statement->issued_at->lessThanOrEqualTo($cutoff)), $paid)),
                $oldest?->issued_at?->setTimezone(config()->string('app.display_timezone'))->toDateString() ?? '',
            ];
        }

        return $rows;
    }

    /** @return list<list<string>> */
    private function renewals(): array
    {
        $rows = [['Empresa', 'Cuenta', 'Plan', 'Estado', 'Renovación', 'Total del siguiente estado con IVA']];
        $subscriptions = Subscription::query()->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionStatus::CancelScheduled])->orderBy('renews_on')->orderBy('id')->get();
        $briefs = $this->companies->many(array_values($subscriptions->map(fn (Subscription $subscription): string => $subscription->tenant_id)->all()));
        $payers = AccountCompany::query()->current()->whereIn('tenant_id', array_keys($briefs))->get(['billing_account_id', 'tenant_id'])->keyBy('tenant_id');
        $accounts = BillingAccount::query()->whereIn('id', $payers->map(fn (AccountCompany $link): string => $link->billing_account_id)->values())->get(['id', 'legal_name'])->keyBy('id');
        $lines = [];

        foreach ($subscriptions as $subscription) {
            try {
                $next = $this->pesos($this->build->handle($subscription->tenant_id, $subscription->renews_on)->totalCents);
            } catch (InvalidStatement) {
                $next = '';
            }

            $lines[] = [$briefs[$subscription->tenant_id]->tradeName ?? '', $accounts->get($payers->get($subscription->tenant_id)->billing_account_id ?? '')->legal_name ?? '', $subscription->plan->label(), $subscription->status->label(), $subscription->renews_on->toDateString(), $next];
        }

        return [...$rows, ...$lines];
    }

    /**
     * @param  Collection<int, Statement>  $statements
     * @param  array<string, int>  $paid
     */
    private function owed(Collection $statements, array $paid): int
    {
        return array_sum(array_map(fn (Statement $statement): int => $statement->total_cents - ($paid[$statement->id] ?? 0), $statements->all()));
    }

    /** 568400 cents as "5684.00": a plain number a spreadsheet adds up. */
    private function pesos(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** @param  list<string>  $cells */
    private function line(array $cells): string
    {
        return implode(',', array_map(fn (string $cell): string => '"'.str_replace('"', '""', CsvCell::safe($cell)).'"', $cells))."\n";
    }
}
