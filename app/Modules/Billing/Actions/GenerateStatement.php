<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Data\StatementDraft;
use App\Modules\Billing\Enums\StatementStatus;
use App\Modules\Billing\Exceptions\InvalidStatement;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\SubscriptionCharge;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class GenerateStatement
{
    public function __construct(private BuildStatement $build) {}

    /**
     * Writes the draft of a company's month, with its lines, and marks the one-time charges it bills, all in one
     * transaction. A month has one statement: asking again returns it as it is and bills nothing twice. It never touches the
     * company's access, dates, restriction or modules.
     *
     * @param  CarbonImmutable  $month  any day of the month.
     *
     * @throws InvalidStatement
     */
    public function handle(string $tenantId, CarbonImmutable $month, ?User $author): Statement
    {
        $existing = $this->existing($tenantId, $month);

        if ($existing !== null) {
            return $existing;
        }

        $draft = $this->build->handle($tenantId, $month);

        try {
            return DB::transaction(fn (): Statement => $this->write($draft, $author));
        } catch (UniqueConstraintViolationException $exception) {
            // Someone made the same month at the same moment: theirs stands.
            return $this->existing($tenantId, $month) ?? throw $exception;
        }
    }

    private function existing(string $tenantId, CarbonImmutable $month): ?Statement
    {
        return Statement::query()
            ->where('tenant_id', $tenantId)
            ->where('period_start', $month->startOfMonth()->toDateString())
            ->whereNull('adjusts_statement_id')
            ->first();
    }

    private function write(StatementDraft $draft, ?User $author): Statement
    {
        $statement = Statement::query()->create([
            'billing_account_id' => $draft->accountId,
            'tenant_id' => $draft->tenantId,
            'period_start' => $draft->periodStart,
            'period_end' => $draft->periodEnd,
            'status' => StatementStatus::Draft,
            'currency' => $draft->currency,
            'catalog_version' => $draft->catalogVersion,
            'vat_percent' => $draft->vatPercent,
            'subtotal_cents' => $draft->subtotalCents,
            'vat_cents' => $draft->vatCents,
            'total_cents' => $draft->totalCents,
            'snapshot' => $draft->snapshot,
            'created_by' => $author?->id,
        ]);

        $billed = [];

        foreach ($draft->lines as $position => $line) {
            $statement->lines()->create([
                'position' => $position + 1,
                'code' => $line->code,
                'label' => $line->label,
                'amount_cents' => $line->amountCents,
                'explanation' => $line->explanation,
                'charge_id' => $line->chargeId,
            ]);

            if ($line->code === 'charge_one_time' && $line->chargeId !== null) {
                $billed[] = $line->chargeId;
            }
        }

        if ($billed !== [] && SubscriptionCharge::query()->whereIn('id', $billed)->whereNull('billed_at')->update(['billed_at' => now()]) !== count($billed)) {
            throw new InvalidStatement('period', 'Un cargo de una sola vez ya se facturó en otro estado de cuenta. Intenta de nuevo.');
        }

        Log::info('billing.statement_generated', ['tenant_id' => $draft->tenantId, 'period' => $draft->periodStart->format('Y-m'), 'total_cents' => $draft->totalCents]);

        return $statement;
    }
}
