import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import AccountDialog from '@/components/billing/account-dialog';
import PageHeader from '@/components/humetria/page-header';
import { Badge } from '@/components/ui/badge';
import { index } from '@/routes/billing/accounts';
import type { AccountDetail } from '@/types';

/** One payer account: who it is and how to reach it. */
export default function Account({ account }: { account: AccountDetail }) {
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
                    {auth.isSuperadmin && <AccountDialog account={account} />}
                </PageHeader>
            </div>
        </>
    );
}
