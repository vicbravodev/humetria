import { usePage } from '@inertiajs/react';
import AccountDialog from '@/components/billing/account-dialog';
import LinkCompanyDialog from '@/components/billing/link-company-dialog';
import PageHeader from '@/components/humetria/page-header';
import { Badge } from '@/components/ui/badge';
import type { AccountDetail, Receivable, SatOption } from '@/types';

/** The head of an account: who it is, its kind and whether it is behind, how to reach it, and (for the superadmin) its actions. */
export default function AccountHeader({
    account,
    receivable,
    taxRegimes,
    cfdiUses,
}: {
    account: AccountDetail;
    receivable: Receivable;
    taxRegimes: SatOption[];
    cfdiUses: SatOption[];
}) {
    const { auth } = usePage().props;

    return (
        <PageHeader
            caption="Cuenta"
            title={account.legal_name}
            status={
                <>
                    <Badge>{account.kind_label}</Badge>
                    {receivable.overdue !== null && (
                        <Badge variant="destructive">
                            Vencida · {receivable.overdue}
                        </Badge>
                    )}
                </>
            }
            description={`${account.rfc} · ${account.contact_name}, ${account.contact_email}`}
        >
            {auth.isSuperadmin && (
                <>
                    <AccountDialog
                        account={account}
                        taxRegimes={taxRegimes}
                        cfdiUses={cfdiUses}
                    />
                    <LinkCompanyDialog accountId={account.id} />
                </>
            )}
        </PageHeader>
    );
}
