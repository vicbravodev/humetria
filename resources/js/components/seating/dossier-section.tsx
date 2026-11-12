import { Badge } from '@/components/ui/badge';
import { describeDate } from '@/lib/calendar-date';
import type { SeatingDossierRow } from '@/types';

const VARIANT = {
    met: 'success',
    warning: 'warning',
    missing: 'destructive',
    pending: 'secondary',
} as const;

/** One requirement as a section of the dossier: its number, state and what exists with its date. It never splits across pages. */
export default function DossierSection({ row }: { row: SeatingDossierRow }) {
    return (
        <section
            aria-label={`Requisito ${row.number}`}
            className="flex break-inside-avoid flex-col gap-3 border-t pt-5"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <h2 className="text-lg leading-6 font-semibold">
                    <span className="text-muted-foreground tabular-nums">
                        {row.number}
                    </span>{' '}
                    {row.label}
                </h2>
                <Badge variant={VARIANT[row.state]}>{row.stateLabel}</Badge>
            </div>
            <ul className="flex flex-col gap-1 text-sm">
                {row.lines.map((line) => (
                    <li key={`${line.text}-${line.date ?? ''}`}>
                        {line.text}
                        {line.date !== null &&
                            ` · ${describeDate(line.date) ?? line.date}`}
                    </li>
                ))}
            </ul>
        </section>
    );
}
