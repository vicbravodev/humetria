import AccountCharges from '@/components/billing/account-charges';
import AccountPayments from '@/components/billing/account-payments';
import AccountStatements from '@/components/billing/account-statements';
import AccountTransfers from '@/components/billing/account-transfers';
import type {
    AccountChargeRow,
    Paginated,
    PaymentRow,
    StatementRow,
    TransferRow,
} from '@/types';

/** The money of the account over time: its statements, the payments it made, the companies that came or left and the extra charges; each only once it has any. */
export default function AccountLedger({
    accountId,
    today,
    statements,
    payments,
    transfers,
    charges,
}: {
    accountId: string;
    today: string;
    statements: Paginated<StatementRow>;
    payments: Paginated<PaymentRow>;
    transfers: Paginated<TransferRow>;
    charges: Paginated<AccountChargeRow>;
}) {
    return (
        <>
            {statements.total > 0 && (
                <AccountStatements
                    accountId={accountId}
                    today={today}
                    statements={statements}
                />
            )}
            {payments.total > 0 && (
                <AccountPayments accountId={accountId} payments={payments} />
            )}
            {transfers.total > 0 && <AccountTransfers transfers={transfers} />}
            {charges.total > 0 && <AccountCharges charges={charges} />}
        </>
    );
}
