import AccountFigure from '@/components/billing/account-figure';
import { paidHint } from '@/lib/billing-account';
import { agingLabel } from '@/lib/billing-aging';
import { countOf } from '@/lib/plural';
import type { AccountYear, Receivable } from '@/types';

/** The three figures of an account (tablero Cuenta de facturación; the design system allows three a screen): what it owes, and what it billed and paid this year, with how late it pays. */
export default function AccountYearFigures({
    receivable,
    year,
}: {
    receivable: Receivable;
    year: AccountYear;
}) {
    return (
        <dl
            aria-label="Cifras de la cuenta"
            className="grid gap-4 sm:grid-cols-3"
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
                hint={paidHint(year.payments, year.average_days)}
            />
        </dl>
    );
}
