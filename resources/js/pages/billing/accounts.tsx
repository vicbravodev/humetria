import { Head, usePage } from '@inertiajs/react';
import AccountDialog from '@/components/billing/account-dialog';
import AccountsBulkBar from '@/components/billing/accounts-bulk-bar';
import AccountsEmpty from '@/components/billing/accounts-empty';
import AccountsExports from '@/components/billing/accounts-exports';
import AccountsTable from '@/components/billing/accounts-table';
import AccountsToolbar from '@/components/billing/accounts-toolbar';
import ReceivableOverview from '@/components/billing/receivable-overview';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import { useIdSelection } from '@/hooks/use-id-selection';
import { chosenOwed } from '@/lib/billing';
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
    months,
    taxRegimes,
    cfdiUses,
}: {
    accounts: Paginated<AccountRow>;
    filters: AccountFilters;
    counts: Record<AccountView, number>;
    receivable: ReceivablePortfolio;
    months: { value: string; label: string }[];
    taxRegimes: SatOption[];
    cfdiUses: SatOption[];
}) {
    const { auth } = usePage().props;
    const selection = useIdSelection(accounts.data.map(({ id }) => id));

    return (
        <>
            <Head title="Cuentas y cobranza" />
            <div className="flex flex-col gap-6 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Cuentas y cobranza"
                    description="Quién paga qué y qué falta por cobrar. Las cuentas son directas o de un consultor que paga por varias empresas."
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
                    <AccountsToolbar
                        filters={filters}
                        counts={counts}
                        months={months}
                    />
                )}
                {accounts.data.length === 0 ? (
                    <AccountsEmpty filtered={counts.all > 0} />
                ) : (
                    <div className="overflow-hidden card p-0">
                        <AccountsTable
                            accounts={accounts.data}
                            selection={selection}
                            canManage={auth.isSuperadmin}
                        />
                        <div className="border-t px-4 py-3 empty:hidden">
                            <Pagination
                                previous="Anterior"
                                next="Siguiente"
                                page={accounts}
                                label="Páginas de cuentas"
                                pinned={false}
                            />
                        </div>
                        {auth.isSuperadmin && (
                            <AccountsBulkBar
                                chosen={selection.chosen}
                                owed={chosenOwed(
                                    accounts.data,
                                    selection.chosen,
                                )}
                                onDone={selection.clear}
                            />
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
