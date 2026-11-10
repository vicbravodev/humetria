import { usePage } from '@inertiajs/react';
import AddChargeDialog from '@/components/billing/add-charge-dialog';
import AccountCompanyPlan from '@/components/billing/account-company-plan';
import SubscriptionDialog from '@/components/billing/subscription-dialog';
import Initials from '@/components/humetria/initials';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { tenantStatusVariant } from '@/lib/tenant-status';
import type { AccountCompanyRow, AccountKind } from '@/types';

/** A company the account pays: who it is, where it stands, what it contracted and what that costs. */
export default function AccountCompanyRowView({
    company,
    accountKind,
    today,
}: {
    company: AccountCompanyRow;
    accountKind: AccountKind;
    today: string;
}) {
    const { auth } = usePage().props;

    return (
        <TableRow>
            <TableCell className="min-w-60 whitespace-normal">
                <div className="flex items-center gap-2.5">
                    <Initials name={company.trade_name} kind="company" />
                    <div className="flex min-w-0 flex-col">
                        <span className="font-medium">
                            {company.trade_name}
                        </span>
                        <span className="text-caption text-muted-foreground tabular-nums">
                            {company.rfc}
                        </span>
                    </div>
                </div>
            </TableCell>
            <TableCell>
                <Badge variant={tenantStatusVariant(company.status)}>
                    {company.status_label}
                </Badge>
            </TableCell>
            <AccountCompanyPlan
                subscription={company.subscription}
                people={company.people}
                workCenters={company.work_centers}
            />
            <TableCell className="w-px text-right">
                {auth.isSuperadmin && (
                    <div className="flex justify-end">
                        {company.subscription !== null && (
                            <AddChargeDialog company={company} />
                        )}
                        <SubscriptionDialog
                            company={company}
                            accountKind={accountKind}
                            today={today}
                        />
                    </div>
                )}
            </TableCell>
        </TableRow>
    );
}
