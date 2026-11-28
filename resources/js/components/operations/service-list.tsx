import HourBars from '@/components/operations/hour-bars';
import StateDot from '@/components/operations/state-dot';
import { Badge } from '@/components/ui/badge';
import type { Service } from '@/lib/operations';
import type { HourBar } from '@/lib/operations-health';

/**
 * The services behind the platform, one per row (tablero Salud): its dot, what it is and what it says now, its last 24 hours
 * when it keeps them, and its state.
 */
export default function ServiceList({
    services,
    limit,
}: {
    services: (Service & { bars: HourBar[] | null })[];
    limit: number;
}) {
    return (
        <section className="card p-0">
            <header className="flex flex-wrap items-center gap-x-3 gap-y-1 border-b px-6 py-4">
                <h2 className="flex-1 text-base font-semibold">Servicios</h2>
                <p className="text-meta text-muted-foreground">
                    Últimas 24 horas · una barra por hora
                </p>
            </header>
            <ul className="divide-y">
                {services.map(({ name, detail, state, bars }) => (
                    <li
                        key={name}
                        className="flex flex-wrap items-center gap-x-3.5 gap-y-2 px-6 py-4"
                    >
                        <StateDot variant={state.variant} />
                        <div className="min-w-0 flex-1 basis-48">
                            <p className="font-medium">{name}</p>
                            <p className="text-meta text-muted-foreground">
                                {detail}
                            </p>
                        </div>
                        {bars !== null && (
                            <HourBars bars={bars} limit={limit} />
                        )}
                        <Badge
                            variant={state.variant}
                            className="justify-center sm:min-w-[124px]"
                        >
                            {state.label}
                        </Badge>
                    </li>
                ))}
            </ul>
        </section>
    );
}
