import AccountFigure from '@/components/billing/account-figure';
import Notice from '@/components/humetria/notice';
import { figureHints } from '@/lib/billing';
import type { AccountSummary } from '@/types';

/**
 * What the account pays across all its companies: the figures first (the individual costs are in the list), and, when it has
 * channel Integral plans, why its discount applies or what is missing for it.
 */
export default function AccountFigures({
    summary,
}: {
    summary: AccountSummary;
}) {
    const hints = figureHints(summary);

    return (
        <section aria-label="Resumen de la cuenta" className="grid gap-4">
            <dl className="grid gap-6 card p-6 sm:grid-cols-2 lg:grid-cols-4">
                <AccountFigure
                    label="Empresas"
                    value={String(summary.companies)}
                    hint={hints.companies}
                />
                <AccountFigure
                    label="Al mes, sin IVA"
                    value={summary.subtotal ?? '—'}
                    hint={hints.subtotal}
                />
                <AccountFigure
                    label="Al mes, con IVA"
                    value={summary.total ?? '—'}
                    hint={hints.total}
                />
                <AccountFigure
                    label="Próxima renovación"
                    value={summary.next_renewal ?? '—'}
                />
            </dl>
            {summary.discount !== null && (
                <Notice>{summary.discount.reason}</Notice>
            )}
        </section>
    );
}
