import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import type { AccountChargeRow } from '@/types';

/** An extra charge: what it is, for which company, how much, from when to when, and who approved it. */
export default function AccountChargeRowView({
    charge,
}: {
    charge: AccountChargeRow;
}) {
    return (
        <TableRow>
            <TableCell className="min-w-56 whitespace-normal">
                <div className="flex flex-col">
                    <span className="font-medium">{charge.description}</span>
                    <span className="text-caption text-muted-foreground">
                        {charge.scope}
                    </span>
                </div>
            </TableCell>
            <TableCell>{charge.trade_name}</TableCell>
            <TableCell>
                <Badge variant="outline">{charge.kind_label}</Badge>
            </TableCell>
            <TableCell className="text-sm font-medium tabular-nums">
                {charge.amount}
            </TableCell>
            <TableCell className="text-sm tabular-nums">
                {charge.ends_on === null
                    ? `Desde ${charge.charge_on}`
                    : `${charge.charge_on} al ${charge.ends_on}`}
            </TableCell>
            <TableCell className="text-sm text-muted-foreground">
                {charge.approved_by}
            </TableCell>
        </TableRow>
    );
}
