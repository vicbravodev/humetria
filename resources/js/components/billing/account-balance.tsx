import AccountFigure from '@/components/billing/account-figure';
import { agingLabel } from '@/lib/billing-aging';
import { countOf } from '@/lib/plural';
import type { Receivable } from '@/types';

/** What the account owes across its issued statements, what of it is past due and how old its oldest open statement is. */
export default function AccountBalance({
    receivable,
}: {
    receivable: Receivable;
}) {
    return (
        <section aria-label="Saldo de la cuenta">
            <dl className="grid grid-cols-2 gap-6 card p-6">
                <AccountFigure
                    label="Saldo por cobrar"
                    value={receivable.outstanding}
                    hint={
                        receivable.open_statements === 0
                            ? 'Sin estados de cuenta pendientes'
                            : `${countOf(receivable.open_statements, 'estado de cuenta', 'estados de cuenta')} con saldo`
                    }
                />
                <AccountFigure
                    label="Vencido"
                    value={receivable.overdue ?? '$0'}
                    hint={
                        receivable.aging === null
                            ? undefined
                            : `Más antiguo: ${agingLabel(receivable.aging).toLowerCase()}`
                    }
                />
            </dl>
        </section>
    );
}
