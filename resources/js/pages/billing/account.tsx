import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import AccountCharges from '@/components/billing/account-charges';
import AccountTransfers from '@/components/billing/account-transfers';
import AccountPayments from '@/components/billing/account-payments';
import AccountStatements from '@/components/billing/account-statements';
import AccountCompaniesEmpty from '@/components/billing/account-companies-empty';
import AccountFigures from '@/components/billing/account-figures';
import AccountCompaniesTable from '@/components/billing/account-companies-table';
import AccountDialog from '@/components/billing/account-dialog';
import LinkCompanyDialog from '@/components/billing/link-company-dialog';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import { Badge } from '@/components/ui/badge';
import { index } from '@/routes/billing/accounts';
import type {
    AccountChargeRow,
    AccountCompanyRow,
    AccountDetail,
    AccountSummary,
    Paginated,
    PaymentRow,
    Receivable,
    StatementRow,
    TransferRow,
} from '@/types';

/** One payer account: who it is, and the companies it pays. */
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
}) {
    const { auth } = usePage().props;
    setLayoutProps({
        breadcrumbs: [
            { title: 'Cuentas', href: index() },
            { title: account.legal_name },
        ],
    });

    return (
        <>
            <Head title={account.legal_name} />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    caption="Cuenta"
                    title={account.legal_name}
                    status={<Badge>{account.kind_label}</Badge>}
                    description={`${account.rfc} · ${account.contact_name}, ${account.contact_email}`}
                >
                    {auth.isSuperadmin && (
                        <>
                            <AccountDialog account={account} />
                            <LinkCompanyDialog accountId={account.id} />
                        </>
                    )}
                </PageHeader>
                {companies.data.length > 0 && (
                    <AccountFigures summary={summary} />
                )}
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
                {charges.total > 0 && <AccountCharges charges={charges} />}
                {statements.total > 0 && (
                    <AccountStatements
                        accountId={account.id}
                        today={today}
                        statements={statements}
                        receivable={receivable}
                    />
                )}
                {transfers.total > 0 && (
                    <AccountTransfers transfers={transfers} />
                )}
                {payments.total > 0 && (
                    <AccountPayments
                        accountId={account.id}
                        payments={payments}
                    />
                )}
            </div>
        </>
    );
}
