import { countOf } from '@/lib/plural';
import type { ScenarioReport } from '@/types';

/** The numbers a negotiation opens with: what the proposal costs in its first year and every year after. */
export default function ReportHeadline({ report }: { report: ScenarioReport }) {
    const { headline } = report;
    const figures = [
        {
            label: 'Costo del primer año',
            value: headline.first_year_text,
            note:
                headline.retroactive_months > 0
                    ? `Incluye ${headline.retroactive_text} de ${countOf(headline.retroactive_months, 'mes', 'meses')} de retroactivo.`
                    : 'Sin retroactivo.',
        },
        {
            label: 'Costo anual recurrente',
            value: headline.recurring_text,
            note: 'Lo que cuesta la propuesta cada año desde su vigencia.',
        },
        {
            label: 'Antes de la propuesta',
            value: headline.before_text,
            note: `A doce meses de la vigencia, ${countOf(headline.people, 'persona costeada', 'personas costeadas')}.`,
        },
        {
            label: 'Con la propuesta',
            value: headline.after_text,
            note: 'A doce meses, sin contar el retroactivo.',
        },
    ];

    return (
        <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {figures.map((figure) => (
                <div key={figure.label} className="grid gap-1 card p-5">
                    <dt className="text-meta text-muted-foreground">
                        {figure.label}
                    </dt>
                    <dd className="text-2xl leading-8 font-semibold tracking-[-0.01em] tabular-nums">
                        {figure.value}
                    </dd>
                    <dd className="text-caption text-muted-foreground">
                        {figure.note}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
