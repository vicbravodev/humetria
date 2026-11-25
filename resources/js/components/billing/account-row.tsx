import { Link } from '@inertiajs/react';
import AccountSuggestedAction from '@/components/billing/account-suggested-action';
import ChooseBox from '@/components/humetria/choose-box';
import Initials from '@/components/humetria/initials';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { agingBadge, agingLabel } from '@/lib/billing-aging';
import { countOf } from '@/lib/plural';
import { show } from '@/routes/billing/accounts';
import type { AccountRow as Account } from '@/types';

/** An account of the list: who pays and who to talk to, how many companies it pays, what it owes, how old that is and what to do next. */
export default function AccountRowView({
    account,
    canManage,
    chosen,
    onChoose,
}: {
    account: Account;
    canManage: boolean;
    chosen: boolean;
    onChoose: (checked: boolean) => void;
}) {
    return (
        <TableRow data-state={chosen ? 'selected' : undefined}>
            {canManage && (
                <TableCell>
                    <ChooseBox
                        checked={chosen}
                        onChange={onChoose}
                        name={`Elegir ${account.legal_name}`}
                    />
                </TableCell>
            )}
            <TableCell className="min-w-60 whitespace-normal">
                <div className="flex items-center gap-2.5">
                    <Initials name={account.legal_name} kind="company" />
                    <div className="flex min-w-0 flex-col">
                        <Link
                            href={show(account.id)}
                            className="touch-hit rounded-sm font-medium underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            {account.legal_name}
                        </Link>
                        <span className="text-caption text-muted-foreground">
                            <span className="tabular-nums">{account.rfc}</span>
                            {' · '}
                            {account.contact_name}
                        </span>
                    </div>
                </div>
            </TableCell>
            <TableCell>
                <Badge
                    variant={
                        account.kind === 'consultant' ? 'default' : 'outline'
                    }
                >
                    {account.kind_label}
                </Badge>
            </TableCell>
            <TableCell className="text-sm tabular-nums">
                {countOf(account.companies, 'empresa', 'empresas')}
            </TableCell>
            <TableCell className="text-right text-sm font-medium tabular-nums">
                {account.outstanding ?? '$0'}
            </TableCell>
            <TableCell>
                <Badge variant={agingBadge(account.aging)}>
                    {agingLabel(account.aging)}
                </Badge>
            </TableCell>
            <TableCell className="text-sm text-muted-foreground tabular-nums">
                {account.last_payment ?? '—'}
            </TableCell>
            <TableCell>
                <AccountSuggestedAction
                    account={account}
                    canRemind={canManage}
                />
            </TableCell>
        </TableRow>
    );
}
