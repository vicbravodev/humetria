import type { ProgramsSummary as Summary } from '@/types';

const STATES: { key: keyof Summary; label: string }[] = [
    { key: 'overdue', label: 'Con acciones vencidas' },
    { key: 'in_progress', label: 'En curso' },
    { key: 'none', label: 'Sin acciones' },
    { key: 'done', label: 'Terminados' },
];

/**
 * The Programas by state, at a glance: what is overdue comes first, since it is what RH looks for here. The detail is a
 * click away, in the list below (pantalla NOM035: cards with a figure and what it counts).
 */
export default function ProgramsSummary({ summary }: { summary: Summary }) {
    return (
        <section
            aria-label="Programas por estado"
            className="grid grid-cols-2 gap-4 lg:grid-cols-4"
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
