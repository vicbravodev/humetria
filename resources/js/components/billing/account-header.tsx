import { usePage } from '@inertiajs/react';
import AccountDialog from '@/components/billing/account-dialog';
import CompanyPickerAction from '@/components/billing/company-picker-action';
import RecordPaymentDialog from '@/components/billing/record-payment-dialog';
import PageHeader from '@/components/humetria/page-header';
import { Badge } from '@/components/ui/badge';
import { accountFacts } from '@/lib/billing-account';
import type {
    AccountCompanyRow,
    AccountDetail,
    Receivable,
    SatOption,
} from '@/types';

/**
 * The head of an account (tablero Cuenta de facturación): who it is, its kind and whether it is behind, its fiscal data and how
 * many companies it pays, and (for the superadmin) its fiscal data, an extra charge and a payment.
 */
export default function AccountHeader({
    account,
    receivable,
    companies,
    companiesTotal,
    today,
    taxRegimes,
    cfdiUses,
}: {
    account: AccountDetail;
    receivable: Receivable;
    companies: AccountCompanyRow[];
    companiesTotal: number;
    today: string;
    taxRegimes: SatOption[];
    cfdiUses: SatOption[];
}) {
    const { auth } = usePage().props;

    return (
        <PageHeader
            title={account.legal_name}
            status={
                <>
                    <Badge variant="outline">{account.kind_label}</Badge>
                    {receivable.overdue !== null && (
                        <Badge variant="destructive">
                            Vencida · {receivable.overdue}
                        </Badge>
                    )}
                </>
            }
            description={accountFacts(account, companiesTotal)}
        >
            {auth.isSuperadmin && (
                <>
                    <AccountDialog
                        account={account}
                        taxRegimes={taxRegimes}
                        cfdiUses={cfdiUses}
                    />
                    <CompanyPickerAction
                        kind="charge"
                        companies={companies.filter(
                            ({ subscription }) => subscription !== null,
                        )}
                        accountKind={account.kind}
                        today={today}
                        size="default"
                    >
                        Agregar cargo
                    </CompanyPickerAction>
                    <RecordPaymentDialog accountId={account.id} today={today} />
                </>
            )}
        </PageHeader>
    );
}
