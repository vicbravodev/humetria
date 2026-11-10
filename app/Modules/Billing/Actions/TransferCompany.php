<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Billing\Data\TransferData;
use App\Modules\Billing\Exceptions\InvalidTransfer;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\CompanyTransfer;
use App\Modules\Billing\Support\TransferGuard;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class TransferCompany
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Moves a company from the account that pays it to another, or to none, from a cut: the old link ends that day and the new
     * one opens, in one transaction. Statements already made stay with the account they were made to; the next ones go to the
     * new payer. The plan follows the company. It grants and removes no access: memberships and the company are not touched.
     *
     * @throws InvalidTransfer
     */
    public function handle(string $tenantId, TransferData $data, User $by): CompanyTransfer
    {
        $reason = trim($data->reason);

        if ($reason === '') {
            throw new InvalidTransfer('reason', 'Di por qué se traslada la empresa.');
        }

        $context = TransferGuard::check($tenantId, $data);

        try {
            return DB::transaction(function () use ($tenantId, $data, $by, $reason, $context): CompanyTransfer {
                $link = AccountCompany::query()->lockForUpdate()->findOrFail($context->link->id);

                if ($link->ends_on !== null) {
                    throw new InvalidTransfer('company', 'Otra persona ya movió esta empresa: revisa quién la paga.');
                }

                $link->update(['ends_on' => $data->effectiveOn]);

                if ($context->to !== null) {
                    AccountCompany::query()->create(['billing_account_id' => $context->to->id, 'tenant_id' => $tenantId, 'starts_on' => $data->effectiveOn]);
                }

                $before = $context->subscription?->profile;

                if ($context->subscription !== null && $context->profileAfter !== $before) {
                    $context->subscription->update(['profile' => $context->profileAfter]);
                }

                $transfer = CompanyTransfer::query()->create([
                    'tenant_id' => $tenantId,
                    'from_account_id' => $context->from->id,
                    'to_account_id' => $context->to?->id,
                    'effective_on' => $data->effectiveOn,
                    'reason' => $reason,
                    'profile_before' => $context->profileAfter === $before ? null : $before?->value,
                    'profile_after' => $context->profileAfter === $before ? null : $context->profileAfter?->value,
                    'created_by' => $by->id,
                ]);

                $moved = ['tenant_id' => $tenantId, 'from' => $context->from->id, 'to' => $context->to?->id, 'effective_on' => $data->effectiveOn->toDateString()];
                Log::info('billing.company_transferred', $moved);
                $this->recordAudit->handle('billing.company_transferred', $transfer, [...$moved, 'reason' => $reason, 'profile_before' => $transfer->profile_before, 'profile_after' => $transfer->profile_after]);

                return $transfer;
            });
        } catch (UniqueConstraintViolationException) {
            throw new InvalidTransfer('company', 'Otra persona ya movió esta empresa: revisa quién la paga.');
        }
    }
}
