import { Link } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { meterText } from '@/lib/interventions';
import { program as programRoute } from '@/routes/tenant/campaigns';
import { cn } from '@/lib/utils';
import type { ResultsProgramCard } from '@/types';

/**
 * The Programa de intervención of the evaluation (num. 8.3; pantalla Eval-Cerrada, the card of the Guía I): where it stands,
 * or that the results ask for one and it is not made yet. The page of the Programa is the one that reads the figures.
 */
export default function ProgramCard({
    route,
    program,
    wide,
}: {
    /** The company's slug and the campaign's id. */
    route: [string, string];
    program: ResultsProgramCard;
    /** Alone in its row: title, text and button in one line on a wide screen. */
    wide: boolean;
}) {
    return (
        <section
            aria-labelledby="program-title"
            className={cn(
                'flex flex-col gap-3.5 card p-6',
                wide && 'lg:flex-row lg:items-center lg:gap-6',
            )}
        >
            <div
                className={cn(
                    'flex flex-wrap items-center gap-3',
                    wide && 'lg:shrink-0',
                )}
            >
                <span className="inline-flex size-9 items-center justify-center rounded-[10px] bg-accent text-primary">
                    <ClipboardList aria-hidden="true" className="size-[18px]" />
                </span>
                <h2
                    id="program-title"
                    className="text-lg leading-7 font-semibold"
                >
                    Programa de intervención
                </h2>
                {program.state !== null && (
                    <Badge variant="secondary">{program.state.label}</Badge>
                )}
            </div>
            <p
                className={cn(
                    'text-sm text-muted-foreground',
                    wide && 'lg:flex-1',
                )}
            >
                {program.exists && program.meter !== null
                    ? `${meterText(program.meter)}. Qué se atiende, con qué acciones, fechas y responsables.`
                    : 'Hay cuestionarios en riesgo medio o más: la norma pide un Programa con acciones, fechas y responsable (numeral 8.3).'}
            </p>
            <Button
                asChild
                variant="outline"
                size="sm"
                className={cn('w-fit', wide && 'lg:shrink-0')}
            >
                <Link href={programRoute(route)}>
                    {program.exists ? 'Ver el Programa' : 'Crear el Programa'}
                </Link>
            </Button>
        </section>
    );
}
