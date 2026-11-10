import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import AccountCompaniesEmpty from '@/components/billing/account-companies-empty';
import AccountCompaniesTable from '@/components/billing/account-companies-table';
import AccountDialog from '@/components/billing/account-dialog';
import LinkCompanyDialog from '@/components/billing/link-company-dialog';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import { Badge } from '@/components/ui/badge';
import { index } from '@/routes/billing/accounts';
import type { AccountCompanyRow, AccountDetail, Paginated } from '@/types';

/** One payer account: who it is, and the companies it pays. */
export default function Account({
    account,
    companies,
}: {
    account: AccountDetail;
    companies: Paginated<AccountCompanyRow>;
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
                {companies.data.length === 0 ? (
                    <AccountCompaniesEmpty />
                ) : (
                    <AccountCompaniesTable companies={companies.data} />
                )}
                <Pagination
                    previous="Ver más recientes"
                    next="Ver más antiguas"
                    page={companies}
                    label="Páginas de empresas de la cuenta"
                />
            </div>
        </>
    );
}
