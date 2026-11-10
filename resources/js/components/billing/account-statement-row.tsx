import { Link } from '@inertiajs/react';
import StatementStateBadge from '@/components/billing/statement-state-badge';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { show } from '@/routes/billing/statements';
import type { StatementRow } from '@/types';

/** A statement of the account: its month and company, what it adds up to, what is paid and what is left. */
export default function AccountStatementRowView({
    statement,
}: {
    statement: StatementRow;
}) {
    return (
        <TableRow>
            <TableCell className="min-w-44 whitespace-normal">
                <div className="flex flex-col">
                    <Link
                        href={show(statement.id)}
                        className="inline-flex min-h-11 items-center font-medium underline-offset-4 hover:underline"
                    >
                        {statement.period}
                    </Link>
                    <span className="text-caption text-muted-foreground">
                        {statement.is_adjustment ? (
                            <Badge variant="outline">Ajuste</Badge>
                        ) : null}
                    </span>
                </div>
            </TableCell>
            <TableCell>{statement.trade_name}</TableCell>
            <TableCell className="text-sm font-medium tabular-nums">
                {statement.total}
            </TableCell>
            <TableCell className="text-sm tabular-nums">
                {statement.paid ?? '—'}
            </TableCell>
            <TableCell className="text-sm font-medium tabular-nums">
                {statement.balance ?? '—'}
            </TableCell>
            <TableCell>
                <StatementStateBadge
                    state={statement.state}
                    label={statement.state_label}
                />
            </TableCell>
        </TableRow>
    );
}
