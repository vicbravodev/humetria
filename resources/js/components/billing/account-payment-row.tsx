import ApplyPaymentDialog from '@/components/billing/apply-payment-dialog';
import ReversePaymentDialog from '@/components/billing/reverse-payment-dialog';
import { Badge } from '@/components/ui/badge';
import { paymentNote } from '@/lib/billing-account';
import type { PaymentRow } from '@/types';

/** A payment of the account, in a line: its reference, its day and the months it settled, how much came in, and what can be done with it. */
export default function AccountPaymentRowView({
    accountId,
    payment,
    canManage,
}: {
    accountId: string;
    payment: PaymentRow;
    canManage: boolean;
}) {
    return (
        <li className="flex flex-wrap items-center gap-x-3 gap-y-1 px-6 py-3.5">
            <div className="min-w-0 flex-1">
                <p className="font-medium">{payment.reference}</p>
                <p className="text-sm text-muted-foreground">
                    {paymentNote(payment)}
                </p>
                {payment.note !== null && (
                    <p className="text-caption text-muted-foreground">
                        {payment.note} · registró {payment.recorded_by}
                    </p>
                )}
            </div>
            <span className="font-medium tabular-nums">{payment.amount}</span>
            {payment.reversed ? (
                <Badge variant="outline">Revertido</Badge>
            ) : (
                canManage && (
                    <div className="flex">
                        {payment.can_apply && (
                            <ApplyPaymentDialog
                                accountId={accountId}
                                payment={payment}
                            />
                        )}
                        <ReversePaymentDialog payment={payment} />
                    </div>
                )
            )}
        </li>
    );
}
