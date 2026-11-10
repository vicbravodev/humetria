import ReversePaymentDialog from '@/components/billing/reverse-payment-dialog';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import type { PaymentRow } from '@/types';

/** A payment of the account: its reference and day, how much came in, how much of it was applied, and who recorded it. */
export default function AccountPaymentRowView({
    payment,
    canManage,
}: {
    payment: PaymentRow;
    canManage: boolean;
}) {
    return (
        <TableRow>
            <TableCell className="min-w-48 whitespace-normal">
                <div className="flex flex-col">
                    <span className="font-medium">{payment.reference}</span>
                    <span className="text-caption text-muted-foreground">
                        {payment.reversed
                            ? `Revertido: ${payment.reversal_reason ?? ''}`
                            : (payment.note ?? '')}
                    </span>
                </div>
            </TableCell>
            <TableCell className="text-sm tabular-nums">
                {payment.paid_on}
            </TableCell>
            <TableCell className="text-sm font-medium tabular-nums">
                {payment.amount}
            </TableCell>
            <TableCell className="text-sm tabular-nums">
                {payment.applied}
            </TableCell>
            <TableCell className="text-sm tabular-nums">
                {payment.unapplied}
            </TableCell>
            <TableCell className="text-sm text-muted-foreground">
                {payment.recorded_by}
            </TableCell>
            <TableCell className="w-px text-right">
                {payment.reversed ? (
                    <Badge variant="outline">Revertido</Badge>
                ) : (
                    canManage && <ReversePaymentDialog payment={payment} />
                )}
            </TableCell>
        </TableRow>
    );
}
