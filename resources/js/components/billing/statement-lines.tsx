import QuoteTotalRow from '@/components/billing/quote-total-row';
import type { StatementDetail, StatementLine } from '@/types';

/** What the statement bills: each line with how its amount was reached, then the subtotal, the VAT, the total and what is paid. */
export default function StatementLines({
    statement,
    lines,
}: {
    statement: StatementDetail;
    lines: StatementLine[];
}) {
    return (
        <section
            aria-labelledby="statement-lines"
            className="grid gap-4 card p-6"
        >
            <h2
                id="statement-lines"
                className="text-xl font-semibold tracking-[-0.01em]"
            >
                Conceptos
            </h2>
            <ul className="grid gap-3">
                {lines
                    .filter((line) => line.code !== 'vat')
                    .map((line) => (
                        <li
                            key={`${line.code}-${line.label}`}
                            className="grid gap-0.5"
                        >
                            <div className="flex justify-between gap-4">
                                <span className="font-medium">
                                    {line.label}
                                </span>
                                <span className="tabular-nums">
                                    {line.amount}
                                </span>
                            </div>
                            <p className="text-caption text-muted-foreground">
                                {line.explanation}
                            </p>
                        </li>
                    ))}
            </ul>
            <dl className="grid gap-1 border-t pt-3 text-sm">
                <QuoteTotalRow label="Subtotal" amount={statement.subtotal} />
                <QuoteTotalRow
                    label={`IVA ${statement.vat_percent}%`}
                    amount={statement.vat}
                />
                <QuoteTotalRow label="Total" amount={statement.total} strong />
                {statement.paid !== null && (
                    <>
                        <QuoteTotalRow label="Pagado" amount={statement.paid} />
                        <QuoteTotalRow
                            label="Saldo"
                            amount={statement.balance}
                            strong
                        />
                    </>
                )}
            </dl>
        </section>
    );
}
