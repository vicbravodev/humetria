import { Head, usePage } from '@inertiajs/react';
import AccountDialog from '@/components/billing/account-dialog';
import AccountsEmpty from '@/components/billing/accounts-empty';
import AccountsExports from '@/components/billing/accounts-exports';
import AccountsTable from '@/components/billing/accounts-table';
import AccountsToolbar from '@/components/billing/accounts-toolbar';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import ReceivableOverview from '@/components/billing/receivable-overview';
import { accountsSummary } from '@/lib/billing';
import type {
    AccountFilters,
    AccountRow,
    AccountView,
    Paginated,
    ReceivablePortfolio,
    SatOption,
} from '@/types';

/** Who pays what and what is left to collect (tablero Cuentas y cobranza): the balance by age, then the accounts with what each owes and the next step. */
export default function Accounts({
    accounts,
    filters,
    counts,
    receivable,
    taxRegimes,
    cfdiUses,
}: {
    accounts: Paginated<AccountRow>;
    filters: AccountFilters;
    counts: Record<AccountView, number>;
    receivable: ReceivablePortfolio;
    taxRegimes: SatOption[];
    cfdiUses: SatOption[];
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Cuentas y cobranza" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    caption="Clientes"
                    title="Cuentas y cobranza"
                    description={accountsSummary(counts.all)}
                >
                    <AccountsExports />
                    {/* Support reads who pays what; only the superadmin changes it. */}
                    {auth.isSuperadmin && (
                        <AccountDialog
                            taxRegimes={taxRegimes}
                            cfdiUses={cfdiUses}
                        />
                    )}
                </PageHeader>
                {counts.all > 0 && (
                    <ReceivableOverview receivable={receivable} />
                )}
                {counts.all > 0 && (
                    <AccountsToolbar filters={filters} counts={counts} />
                )}
                {accounts.data.length === 0 ? (
                    <AccountsEmpty filtered={counts.all > 0} />
                ) : (
                    <AccountsTable accounts={accounts.data} />
                )}
                <Pagination
                    previous="Ver más recientes"
                    next="Ver más antiguas"
                    page={accounts}
                    label="Páginas de cuentas"
                />
            </div>
        </>
    );
}
