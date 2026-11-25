import { Head, setLayoutProps } from '@inertiajs/react';
import AccountBalance from '@/components/billing/account-balance';
import AccountCompaniesEmpty from '@/components/billing/account-companies-empty';
import AccountCompaniesTable from '@/components/billing/account-companies-table';
import AccountFigures from '@/components/billing/account-figures';
import AccountHeader from '@/components/billing/account-header';
import AccountLedger from '@/components/billing/account-ledger';
import Pagination from '@/components/humetria/pagination';
import { index } from '@/routes/billing/accounts';
import type {
    AccountChargeRow,
    AccountCompanyRow,
    AccountDetail,
    AccountSummary,
    Paginated,
    PaymentRow,
    Receivable,
    SatOption,
    StatementRow,
    TransferRow,
} from '@/types';

/**
 * One payer account (tablero Cuenta de facturación): who it is and whether it is behind, the companies it pays, then its
 * statements, payments, transfers and charges, with what it owes and what it pays a month in the side panel.
 */
export default function Account({
    account,
    companies,
    charges,
    statements,
    payments,
    receivable,
    transfers,
    summary,
    today,
    taxRegimes,
    cfdiUses,
}: {
    account: AccountDetail;
    companies: Paginated<AccountCompanyRow>;
    charges: Paginated<AccountChargeRow>;
    statements: Paginated<StatementRow>;
    payments: Paginated<PaymentRow>;
    receivable: Receivable;
    transfers: Paginated<TransferRow>;
    summary: AccountSummary;
    today: string;
    taxRegimes: SatOption[];
    cfdiUses: SatOption[];
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Cuentas y cobranza', href: index() },
            { title: account.legal_name },
        ],
    });

    return (
        <>
            <Head title={account.legal_name} />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <AccountHeader
                    account={account}
                    receivable={receivable}
                    taxRegimes={taxRegimes}
                    cfdiUses={cfdiUses}
                />
                <div className="grid gap-8 2xl:grid-cols-[minmax(0,1fr)_360px] 2xl:items-start">
                    <aside
                        aria-label="Resumen de la cuenta"
                        className="grid gap-4 lg:grid-cols-2 2xl:order-last 2xl:grid-cols-1"
                    >
                        <AccountBalance receivable={receivable} />
                        {companies.data.length > 0 && (
                            <AccountFigures summary={summary} />
                        )}
                    </aside>
                    <div className="flex min-w-0 flex-col gap-8">
                        {companies.data.length === 0 ? (
                            <AccountCompaniesEmpty />
                        ) : (
                            <AccountCompaniesTable
                                companies={companies.data}
                                accountKind={account.kind}
                                today={today}
                            />
                        )}
                        <Pagination
                            previous="Ver más recientes"
                            next="Ver más antiguas"
                            page={companies}
                            label="Páginas de empresas de la cuenta"
                        />
                        <AccountLedger
                            accountId={account.id}
                            today={today}
                            statements={statements}
                            payments={payments}
                            transfers={transfers}
                            charges={charges}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}
