import { Link } from '@inertiajs/react';
import Initials from '@/components/humetria/initials';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { countOf } from '@/lib/plural';
import { show } from '@/routes/billing/accounts';
import type { AccountRow as Account } from '@/types';

/** An account of the list: who pays, their RFC and contact, and how many companies they pay. */
export default function AccountRowView({ account }: { account: Account }) {
    return (
        <TableRow>
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
                        <span className="text-caption text-muted-foreground tabular-nums">
                            {account.rfc}
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
            <TableCell className="whitespace-normal">
                <div className="flex flex-col">
                    <span>{account.contact_name}</span>
                    <span className="text-caption text-muted-foreground">
                        {account.contact_email}
                    </span>
                </div>
            </TableCell>
            <TableCell className="text-sm tabular-nums">
                {account.overdue ?? (
                    <span className="text-muted-foreground">Al corriente</span>
                )}
            </TableCell>
        </TableRow>
    );
}
