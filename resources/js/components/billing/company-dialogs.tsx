import AddChargeDialog from '@/components/billing/add-charge-dialog';
import GenerateStatementDialog from '@/components/billing/generate-statement-dialog';
import SubscriptionDialog from '@/components/billing/subscription-dialog';
import TransferCompanyDialog from '@/components/billing/transfer-company-dialog';
import type {
    AccountCompanyRow,
    AccountKind,
    CompanyDialogKind,
} from '@/types';

/** The dialogs of a company the account pays, at most one open: its plan, a statement, an extra charge or its transfer. */
export default function CompanyDialogs({
    company,
    accountKind,
    today,
    open,
    onClose,
}: {
    company: AccountCompanyRow;
    accountKind: AccountKind;
    today: string;
    open: CompanyDialogKind | null;
    onClose: () => void;
}) {
    const opened = (kind: CompanyDialogKind) => ({
        open: open === kind,
        onOpenChange: (next: boolean) => {
            if (!next) {
                onClose();
            }
        },
    });

    return (
        <>
            <SubscriptionDialog
                company={company}
                accountKind={accountKind}
                today={today}
                {...opened('plan')}
            />
            <TransferCompanyDialog
                company={company}
                today={today}
                {...opened('transfer')}
            />
            {company.subscription !== null && (
                <>
                    <GenerateStatementDialog
                        company={company}
                        today={today}
                        {...opened('statement')}
                    />
                    <AddChargeDialog company={company} {...opened('charge')} />
                </>
            )}
        </>
    );
}
