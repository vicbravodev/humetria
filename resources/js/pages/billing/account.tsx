import { Head, setLayoutProps } from '@inertiajs/react';
import AccountCompaniesCard from '@/components/billing/account-companies-card';
import AccountHeader from '@/components/billing/account-header';
import AccountLedger from '@/components/billing/account-ledger';
import AccountUpcoming from '@/components/billing/account-upcoming';
import AccountYearFigures from '@/components/billing/account-year-figures';
import { index } from '@/routes/billing/accounts';
import type {
    AccountChargeRow,
    AccountCompanyRow,
    AccountDetail,
    AccountSummary,
    AccountYear,
    Paginated,
    PaymentRow,
    Receivable,
    SatOption,
    StatementRow,
    TransferRow,
} from '@/types';

/**
 * One payer account (tablero Cuenta de facturación): who it is and whether it is behind, its four figures, then its statements
 * and payments on the left and, on the right, the companies it pays and what comes next.
 */
export default function Account({
    account,
    companies,
    charges,
    statements,
    statementsView,
    payments,
    receivable,
    transfers,
    summary,
    year,
    today,
    taxRegimes,
    cfdiUses,
}: {
    account: AccountDetail;
    companies: Paginated<AccountCompanyRow>;
    charges: Paginated<AccountChargeRow>;
    statements: Paginated<StatementRow>;
    statementsView: 'all' | 'open';
    payments: Paginated<PaymentRow>;
    receivable: Receivable;
    transfers: Paginated<TransferRow>;
    summary: AccountSummary;
    year: AccountYear;
    today: string;
    taxRegimes: SatOption[];
    cfdiUses: SatOption[];
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Cuentas', href: index() },
            { title: account.legal_name },
        ],
    });

    return (
        <>
            <Head title={account.legal_name} />
            <div className="flex flex-col gap-6 px-4 py-6 md:px-10 md:py-8">
                <AccountHeader
                    account={account}
                    receivable={receivable}
                    companies={companies.data}
                    companiesTotal={companies.total}
                    today={today}
                    taxRegimes={taxRegimes}
                    cfdiUses={cfdiUses}
                />
                <AccountYearFigures receivable={receivable} year={year} />
                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        <AccountLedger
                            accountId={account.id}
                            accountKind={account.kind}
                            today={today}
                            statements={statements}
                            statementsView={statementsView}
                            payments={payments}
                            transfers={transfers}
                            charges={charges}
                            companies={companies.data}
                        />
                    </div>
                    <aside
                        aria-label="Empresas y próximos cargos"
                        className="flex min-w-0 flex-col gap-6"
                    >
                        <AccountCompaniesCard
                            accountId={account.id}
                            accountKind={account.kind}
                            companies={companies}
                            today={today}
                        />
                        <AccountUpcoming summary={summary} today={today} />
                    </aside>
                </div>
            </div>
        </>
    );
}
