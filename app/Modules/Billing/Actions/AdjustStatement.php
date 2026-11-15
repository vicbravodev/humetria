<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Billing\Data\AdjustmentData;
use App\Modules\Billing\Enums\StatementStatus;
use App\Modules\Billing\Exceptions\InvalidStatement;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Support\Quote;
use App\Modules\Billing\Support\ShortDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class AdjustStatement
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Corrects an issued statement without touching it: another statement of the same company and month, issued at once,
     * that references the original with one line for the amount and one for its VAT (a credit is negative). The VAT uses the
     * rate the original was issued with, rounded half a cent up away from zero.
     *
     * @throws InvalidStatement
     */
    public function handle(Statement $original, AdjustmentData $data, User $author): Statement
    {
        if ($data->amountCents === 0) {
            throw new InvalidStatement('amount', 'El ajuste no puede ser de cero.');
        }

        return DB::transaction(function () use ($original, $data, $author): Statement {
            $locked = Statement::query()->lockForUpdate()->findOrFail($original->id);
            $this->guard($locked, $data);

            $sign = $data->amountCents < 0 ? -1 : 1;
            $vat = $sign * Quote::divide(abs($data->amountCents) * $locked->vat_percent, 100);

            $adjustment = Statement::query()->create([
                'billing_account_id' => $locked->billing_account_id,
                'tenant_id' => $locked->tenant_id,
                'adjusts_statement_id' => $locked->id,
                'period_start' => $locked->period_start,
                'period_end' => $locked->period_end,
                'status' => StatementStatus::Draft,
                'currency' => $locked->currency,
                'catalog_version' => $locked->catalog_version,
                'vat_percent' => $locked->vat_percent,
                'subtotal_cents' => $data->amountCents,
                'vat_cents' => $vat,
                'total_cents' => $data->amountCents + $vat,
                'snapshot' => ['adjusts_statement_id' => $locked->id],
                'reason' => $data->reason,
                'created_by' => $author->id,
            ]);

            $adjustment->lines()->create(['position' => 1, 'code' => 'adjustment', 'label' => $data->description, 'amount_cents' => $data->amountCents, 'explanation' => 'Ajuste al estado de cuenta de '.ShortDate::month($locked->period_start).": {$data->reason}"]);
            $adjustment->lines()->create(['position' => 2, 'code' => 'vat', 'label' => "IVA {$locked->vat_percent}%", 'amount_cents' => $vat, 'explanation' => "{$locked->vat_percent}% sobre el ajuste"]);
            $adjustment->update(['status' => StatementStatus::Issued, 'issued_at' => now(), 'issued_by' => $author->id]);

            Log::info('billing.statement_adjusted', ['tenant_id' => $locked->tenant_id, 'adjusts_statement_id' => $locked->id, 'total_cents' => $adjustment->total_cents]);
            $this->recordAudit->handle('billing.statement_adjusted', $adjustment, [
                'tenant_id' => $locked->tenant_id,
                'billing_account_id' => $locked->billing_account_id,
                'adjusts_statement_id' => $locked->id,
                'total_cents' => $adjustment->total_cents,
            ]);

            return $adjustment;
        });
    }

    /** @throws InvalidStatement */
    private function guard(Statement $original, AdjustmentData $data): void
    {
        if ($original->adjusts_statement_id !== null) {
            throw new InvalidStatement('statement', 'Ajusta el estado de cuenta original, no un ajuste.');
        }

        if ($original->status !== StatementStatus::Issued) {
            throw new InvalidStatement('statement', 'Solo se ajusta un estado de cuenta emitido.');
        }

        $sums = Statement::query()->where('id', $original->id)->orWhere('adjusts_statement_id', $original->id)->pluck('total_cents')->all();
        $vat = Quote::divide(abs($data->amountCents) * $original->vat_percent, 100);
        $new = $data->amountCents + ($data->amountCents < 0 ? -$vat : $vat);

        if (array_sum($sums) + $new < 0) {
            throw new InvalidStatement('amount', 'El crédito es mayor que lo que suma ese estado de cuenta.');
        }
    }
}
