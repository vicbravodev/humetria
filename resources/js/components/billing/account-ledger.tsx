import AccountCharges from '@/components/billing/account-charges';
import AccountPayments from '@/components/billing/account-payments';
import AccountStatements from '@/components/billing/account-statements';
import AccountTransfers from '@/components/billing/account-transfers';
import type {
    AccountChargeRow,
    AccountCompanyRow,
    AccountKind,
    Paginated,
    PaymentRow,
    StatementRow,
    TransferRow,
} from '@/types';

/**
 * The money of the account over time (the left column of tablero Cuenta de facturación): its statements and payments, then
 * the companies that came or left and the extra charges, each of those two only once it has any.
 */
export default function AccountLedger({
    accountId,
    accountKind,
    today,
    statements,
    statementsView,
    payments,
    transfers,
    charges,
    companies,
}: {
    accountId: string;
    accountKind: AccountKind;
    today: string;
    statements: Paginated<StatementRow>;
    statementsView: 'all' | 'open';
    payments: Paginated<PaymentRow>;
    transfers: Paginated<TransferRow>;
    charges: Paginated<AccountChargeRow>;
    companies: AccountCompanyRow[];
}) {
    return (
        <>
            <AccountStatements
                accountId={accountId}
                accountKind={accountKind}
                today={today}
                statements={statements}
                view={statementsView}
                companies={companies}
            />
            <AccountPayments
                accountId={accountId}
                today={today}
                payments={payments}
            />
            {transfers.total > 0 && <AccountTransfers transfers={transfers} />}
            {charges.total > 0 && <AccountCharges charges={charges} />}
        </>
    );
}
