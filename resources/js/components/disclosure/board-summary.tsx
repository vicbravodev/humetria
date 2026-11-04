import type { BoardSummary } from '@/types';

const STATES: { key: keyof BoardSummary; label: string }[] = [
    { key: 'missing', label: 'Centros con faltantes' },
    { key: 'notice', label: 'Centros con avisos' },
    { key: 'met', label: 'Centros al día' },
];

/** The centers by overall state, at a glance: what is missing comes first (pantalla NOM035: cards with a figure). */
export default function BoardSummaryCards({
    summary,
}: {
    summary: BoardSummary;
}) {
    return (
        <section
            aria-label="Centros por estado"
            className="grid grid-cols-1 gap-4 sm:grid-cols-3"
        >
            {STATES.map(({ key, label }) => (
                <div
                    key={key}
                    className="flex flex-col justify-between gap-3 card p-5"
                >
                    <span className="text-sm text-muted-foreground">
                        {label}
                    </span>
                    <span className="text-[32px] leading-10 font-semibold tracking-[-0.02em] tabular-nums">
                        {summary[key]}
                    </span>
                </div>
            ))}
        </section>
    );
}
