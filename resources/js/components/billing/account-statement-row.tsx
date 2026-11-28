import { Link } from '@inertiajs/react';
import StatementStateBadge from '@/components/billing/statement-state-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { show } from '@/routes/billing/statements';
import type { StatementRow } from '@/types';

/** A statement of the account, in a line: its month and company, where it stands, when it was issued and is due, its total and what is left. */
export default function AccountStatementRowView({
    statement,
}: {
    statement: StatementRow;
}) {
    return (
        <TableRow>
            <TableCell>
                <div className="flex flex-col">
                    <span className="flex items-center gap-2 font-medium">
                        {statement.period}
                        {statement.is_adjustment && (
                            <Badge variant="outline">Ajuste</Badge>
                        )}
                    </span>
                    <span className="text-caption text-muted-foreground">
                        {statement.trade_name}
                    </span>
                </div>
            </TableCell>
            <TableCell>
                <StatementStateBadge
                    state={statement.state}
                    label={statement.state_label}
                />
            </TableCell>
            <TableCell className="text-sm tabular-nums">
                {statement.issued_on ?? '—'}
            </TableCell>
            <TableCell className="text-sm tabular-nums">
                {statement.due_on ?? '—'}
            </TableCell>
            <TableCell className="text-right text-sm tabular-nums">
                {statement.total}
            </TableCell>
            <TableCell className="text-right text-sm font-medium tabular-nums">
                {statement.balance ?? '—'}
            </TableCell>
            <TableCell className="w-px text-right">
                <Button variant="ghost" size="sm" asChild>
                    <Link
                        href={show(statement.id)}
                        aria-label={`Abrir ${statement.period} de ${statement.trade_name}`}
                    >
                        Abrir
                    </Link>
                </Button>
            </TableCell>
        </TableRow>
    );
}
