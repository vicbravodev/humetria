import AccountFigure from '@/components/billing/account-figure';
import AgingStrip from '@/components/billing/aging-strip';
import { collectedHint } from '@/lib/billing';
import { countOf } from '@/lib/plural';
import type { ReceivablePortfolio } from '@/types';

/** Where collection stands across all the accounts: what is owed and past due, what came in this month, what renews soon, and how old the balance is. */
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
            <dl className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
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
                <AccountFigure
                    label="Renuevan en 30 días"
                    value={String(receivable.renewing)}
                    hint={`${receivable.renewing_yearly} al año`}
                />
            </dl>
            <AgingStrip aging={receivable.aging} />
        </section>
    );
}
