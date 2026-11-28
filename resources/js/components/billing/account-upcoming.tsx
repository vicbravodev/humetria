import { Info } from 'lucide-react';
import Notice from '@/components/humetria/notice';
import { figureHints } from '@/lib/billing';
import { nextMonth } from '@/lib/billing-account';
import type { AccountSummary } from '@/types';

/**
 * «Próximos cargos» (tablero Cuenta de facturación): what next month's statements add up to with VAT, as the plans stand
 * today, and which company renews next; then why a channel discount applies or not, and what a consultant may see.
 */
export default function AccountUpcoming({
    summary,
    today,
}: {
    summary: AccountSummary;
    today: string;
}) {
    const hints = figureHints(summary);

    return (
        <>
            {summary.companies > 0 && (
                <section
                    aria-labelledby="account-upcoming"
                    className="card p-0"
                >
                    <h2
                        id="account-upcoming"
                        className="border-b px-6 py-4 font-semibold"
                    >
                        Próximos cargos
                    </h2>
                    <dl className="divide-y">
                        <div className="flex flex-wrap items-center gap-x-3 px-6 py-3.5">
                            <dt className="flex-1">
                                Estado de {nextMonth(today)}
                            </dt>
                            <dd className="font-semibold tabular-nums">
                                {summary.total ?? '—'}
                            </dd>
                            <dd className="w-full text-caption text-muted-foreground">
                                {[
                                    summary.subtotal === null
                                        ? undefined
                                        : `${summary.subtotal} sin IVA`,
                                    hints.subtotal,
                                    summary.without_plan === 0
                                        ? undefined
                                        : hints.companies,
                                ]
                                    .filter((hint) => hint !== undefined)
                                    .join(' · ')}
                            </dd>
                        </div>
                        {summary.next_renewal !== null && (
                            <div className="flex items-center gap-3 px-6 py-3.5">
                                <dt className="min-w-0 flex-1">
                                    Renovación
                                    {summary.next_renewal_company !== null &&
                                        ` · ${summary.next_renewal_company}`}
                                </dt>
                                <dd className="tabular-nums">
                                    {summary.next_renewal}
                                </dd>
                            </div>
                        )}
                    </dl>
                </section>
            )}
            {summary.discount !== null && (
                <Notice>{summary.discount.reason}</Notice>
            )}
            <p className="flex gap-3 rounded-xl bg-accent px-4 py-3.5 text-meta text-accent-foreground">
                <Info aria-hidden className="mt-0.5 size-[18px] shrink-0" />
                El consultor ve solo el costo de sus empresas, nunca sus
                respuestas ni personas.
            </p>
        </>
    );
}
