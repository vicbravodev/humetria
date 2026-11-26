import AccountFigure from '@/components/billing/account-figure';
import AgingStrip from '@/components/billing/aging-strip';
import { collectedHint, renewingLine } from '@/lib/billing';
import { countOf } from '@/lib/plural';
import type { ReceivablePortfolio } from '@/types';

/** Where collection stands across all the accounts (three figures, as the design system allows): what is owed and past due, what came in this month, how old the balance is and what renews soon. */
export default function ReceivableOverview({
    receivable,
}: {
    receivable: ReceivablePortfolio;
}) {
    return (
        <section
            aria-label="Resumen de cobranza"
            className="grid gap-6 card p-6"
        >
            <dl className="grid gap-6 sm:grid-cols-3">
                <AccountFigure
                    label="Por cobrar"
                    value={receivable.outstanding}
                    hint={countOf(
                        receivable.open_statements,
                        'estado abierto',
                        'estados abiertos',
                    )}
                />
                <AccountFigure
                    label="Vencido"
                    value={receivable.overdue}
                    hint={countOf(
                        receivable.overdue_accounts,
                        'cuenta',
                        'cuentas',
                    )}
                />
                <AccountFigure
                    label={`Cobrado en ${receivable.month}`}
                    value={receivable.collected}
                    hint={collectedHint(
                        receivable.collected_change,
                        receivable.previous_month,
                    )}
                />
            </dl>
            <AgingStrip aging={receivable.aging} />
            {receivable.renewing > 0 && (
                <p className="text-sm text-muted-foreground">
                    {renewingLine(
                        receivable.renewing,
                        receivable.renewing_yearly,
                    )}
                </p>
            )}
        </section>
    );
}
