import { cn } from '@/lib/utils';

/**
 * «Tu ciclo» of NOM-035 (board V3-AlDia): today, the notice 60 days before and the new evaluation due by the end of the
 * two-year cycle (num. 7.9).
 */
export default function RouteCycle({ until }: { until: string }) {
    const points = [
        { when: 'Hoy', what: 'Seguimiento', now: true },
        { when: '60 días antes', what: 'Te avisamos', now: false },
        { when: until, what: 'Nueva evaluación', now: false },
    ];

    return (
        <aside aria-labelledby="cycle" className="flex flex-col gap-4 card p-6">
            <h2 id="cycle" className="text-lg font-semibold">
                Tu ciclo
            </h2>
            <ol className="grid grid-cols-3 gap-2">
                {points.map((point) => (
                    <li key={point.what} className="flex flex-col gap-1.5">
                        <span
                            aria-hidden
                            className={cn(
                                'size-6 rounded-full border-2',
                                point.now
                                    ? 'border-primary ring-4 ring-accent'
                                    : 'border-input',
                            )}
                        />
                        <span className="text-sm font-medium">
                            {point.when}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {point.what}
                        </span>
                    </li>
                ))}
            </ol>
            <p className="text-sm text-muted-foreground">
                Te avisamos 60 días antes. Cuando empiece el nuevo ciclo, tu
                política y tu programa se copian como punto de partida.
            </p>
        </aside>
    );
}
