import ColumnChart from '@/components/humetria/column-chart';
import { pesos } from '@/lib/overview';
import { billedColumns } from '@/lib/overview-today';

/** «Ingreso mensual contratado» (tablero Hoy): what the paying plans contract now, and what was billed month by month. */
export default function BilledCard({
    revenueCents,
    billedByMonth,
}: {
    revenueCents: number;
    billedByMonth: { month: string; cents: number }[];
}) {
    return (
        <section className="flex min-w-0 flex-col gap-5 card p-6">
            <div className="flex flex-wrap items-baseline justify-between gap-3">
                <h2 className="text-lg font-semibold">
                    Ingreso mensual contratado
                </h2>
                <span className="text-sm text-muted-foreground">
                    Antes de IVA
                </span>
            </div>
            <p className="text-[32px] leading-10 font-semibold tracking-[-0.01em] tabular-nums">
                {pesos(revenueCents)}
            </p>
            {billedByMonth.every(({ cents }) => cents === 0) ? (
                <p className="text-sm text-muted-foreground">
                    Todavía no hay estados de cuenta emitidos en el último año.
                </p>
            ) : (
                <ColumnChart
                    label="Lo facturado cada mes, el último año"
                    columns={billedColumns(billedByMonth)}
                />
            )}
        </section>
    );
}
