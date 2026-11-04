import SummaryTable from '@/components/complaints/summary-table';
import type { ChannelSummary as Summary } from '@/types';

/**
 * The channel's numbers, to show that it operates: by type and by status, with the minimum of people per group. Never by
 * center, category or who it is against: that would say who used it.
 */
export default function ChannelSummary({ summary }: { summary: Summary }) {
    return (
        <section className="flex flex-col gap-3">
            <h2 className="text-lg font-semibold">
                Casos recibidos:{' '}
                {summary.total === null
                    ? `menos de ${summary.minimum}`
                    : summary.total}
            </h2>
            <p className="text-sm text-muted-foreground">
                Una cifra menor a {summary.minimum} no se muestra, y a veces
                tampoco la de al lado, para que no se pueda deducir quién usó el
                canal. Los casos que ya se purgaron cuentan.
            </p>
            <div className="grid gap-4 md:grid-cols-2">
                <SummaryTable
                    title="Por tipo"
                    cells={summary.byType}
                    minimum={summary.minimum}
                />
                <SummaryTable
                    title="Por estatus"
                    cells={summary.byStatus}
                    minimum={summary.minimum}
                />
            </div>
        </section>
    );
}
