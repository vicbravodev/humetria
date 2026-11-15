<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Modules\Billing\Enums\StatementStatus;
use App\Modules\Billing\Exceptions\InvalidStatement;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\SubscriptionCharge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class DiscardStatement
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Deletes a draft with its lines and gives back the one-time charges it billed, so the next draft of that month bills them.
     * An issued statement is never deleted: it is corrected with an adjustment. It changes nothing of the company's access.
     *
     * @throws InvalidStatement
     */
    public function handle(Statement $statement): void
    {
        DB::transaction(function () use ($statement): void {
            $locked = Statement::query()->lockForUpdate()->findOrFail($statement->id);

            if ($locked->status !== StatementStatus::Draft) {
                throw new InvalidStatement('statement', 'Solo se descarta un borrador: uno emitido se corrige con un ajuste.');
            }

            $released = SubscriptionCharge::query()
                ->whereIn('id', $locked->lines()->where('code', 'charge_one_time')->select('charge_id'))
                ->update(['billed_at' => null]);

            $locked->lines()->delete();
            $locked->delete();

            Log::info('billing.statement_discarded', ['tenant_id' => $locked->tenant_id, 'period' => $locked->period_start->format('Y-m'), 'total_cents' => $locked->total_cents]);
            $this->recordAudit->handle('billing.statement_discarded', $locked, [
                'tenant_id' => $locked->tenant_id,
                'billing_account_id' => $locked->billing_account_id,
                'period' => $locked->period_start->format('Y-m'),
                'total_cents' => $locked->total_cents,
                'released_charges' => $released,
            ]);
        });
    }
}
