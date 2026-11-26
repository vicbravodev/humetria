import AccountFigure from '@/components/billing/account-figure';
import { averagePaid } from '@/lib/billing-account';
import { agingLabel } from '@/lib/billing-aging';
import { countOf } from '@/lib/plural';
import type { AccountYear, Receivable } from '@/types';

/** The four figures of an account (tablero Cuenta de facturación): what it owes, what it billed and paid this year, and how late it pays. */
export default function AccountYearFigures({
    receivable,
    year,
}: {
    receivable: Receivable;
    year: AccountYear;
}) {
    const average = averagePaid(year.average_days);

    return (
        <dl
            aria-label="Cifras de la cuenta"
            className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <AccountFigure
                className="card p-6"
                label="Saldo"
                value={receivable.outstanding}
                hint={
                    receivable.aging === null
                        ? 'Sin estados de cuenta pendientes'
                        : `Más antiguo: ${agingLabel(receivable.aging).toLowerCase()}`
                }
            />
            <AccountFigure
                className="card p-6"
                label={`Facturado en ${year.year}`}
                value={year.billed}
                hint={countOf(year.billed_statements, 'estado', 'estados')}
            />
            <AccountFigure
                className="card p-6"
                label={`Pagado en ${year.year}`}
                value={year.paid}
                hint={countOf(year.payments, 'pago', 'pagos')}
            />
            <AccountFigure
                className="card p-6"
                label="Promedio de pago"
                value={average.value}
                hint={average.hint}
            />
        </dl>
    );
}
