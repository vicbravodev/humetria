<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Billing\Enums\StatementStatus;
use App\Modules\Billing\Exceptions\InvalidStatement;
use App\Modules\Billing\Models\Statement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class IssueStatement
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Issues a draft: from then on the statement and its lines are immutable (the database refuses any change), and a
     * correction is another statement. It changes nothing of the company's access: that is Tenancy's.
     *
     * @throws InvalidStatement
     */
    public function handle(Statement $statement, User $issuer): Statement
    {
        return DB::transaction(function () use ($statement, $issuer): Statement {
            $locked = Statement::query()->lockForUpdate()->findOrFail($statement->id);

            if ($locked->status !== StatementStatus::Draft) {
                throw new InvalidStatement('statement', 'Este estado de cuenta ya está emitido.');
            }

            $locked->update(['status' => StatementStatus::Issued, 'issued_at' => now(), 'issued_by' => $issuer->id]);

            Log::info('billing.statement_issued', ['tenant_id' => $locked->tenant_id, 'period' => $locked->period_start->format('Y-m'), 'total_cents' => $locked->total_cents]);
            $this->recordAudit->handle('billing.statement_issued', $locked, [
                'tenant_id' => $locked->tenant_id,
                'billing_account_id' => $locked->billing_account_id,
                'period' => $locked->period_start->format('Y-m'),
                'total_cents' => $locked->total_cents,
            ]);

            return $locked;
        });
    }
}
