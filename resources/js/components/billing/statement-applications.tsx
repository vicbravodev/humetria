import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { show } from '@/routes/billing/statements';
import type { AppliedPayment, StatementAdjustment } from '@/types';

/** The payments applied to the statement and the adjustments that correct it; both are short. */
export default function StatementApplications({
    payments,
    adjustments,
}: {
    payments: AppliedPayment[];
    adjustments: StatementAdjustment[];
}) {
    return (
        <section
            aria-labelledby="statement-applications"
            className="grid gap-4 card p-6"
        >
            <h2
                id="statement-applications"
                className="text-xl font-semibold tracking-[-0.01em]"
            >
                Pagos y ajustes
            </h2>
            {payments.length === 0 && adjustments.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Aún no se aplica ningún pago ni hay ajustes.
                </p>
            )}
            <ul className="grid gap-2 text-sm">
                {payments.map((payment) => (
                    <li
                        key={payment.payment_id}
                        className="flex flex-wrap items-center justify-between gap-2"
                    >
                        <span>
                            Pago {payment.reference} · {payment.paid_on}
                            {payment.reversed && (
                                <Badge variant="outline" className="ml-2">
                                    Revertido
                                </Badge>
                            )}
                        </span>
                        <span className="tabular-nums">{payment.amount}</span>
                    </li>
                ))}
                {adjustments.map((adjustment) => (
                    <li
                        key={adjustment.id}
                        className="flex flex-wrap items-center justify-between gap-2"
                    >
                        <Link
                            href={show(adjustment.id)}
                            className="inline-flex min-h-11 items-center underline-offset-4 hover:underline"
                        >
                            Ajuste: {adjustment.reason}
                        </Link>
                        <span className="tabular-nums">{adjustment.total}</span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
