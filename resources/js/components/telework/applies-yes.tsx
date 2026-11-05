import { Link } from '@inertiajs/react';
import { Laptop } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ROUTE_EFFORT } from '@/lib/telework/applies';
import type { NavItem } from '@/types';

/** «Sí te aplica» (board V2-Aplica): the six stages ahead, who does each and about how long, and the way in. */
export default function AppliesYes({
    routeHref,
    onRestart,
}: {
    routeHref: NavItem['href'];
    onRestart: () => void;
}) {
    return (
        <div className="flex flex-col gap-5">
            <span className="inline-flex size-14 items-center justify-center rounded-full bg-accent text-primary">
                <Laptop aria-hidden className="size-7" />
            </span>
            <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em]">
                Sí te aplica. Esta es tu ruta.
            </h1>
            <p className="text-lg leading-7 text-muted-foreground">
                Son 6 etapas. Tú haces unas 3 horas de trabajo, repartidas en
                las semanas que quieras; el resto lo hacen las personas y la
                Comisión desde su portal.
            </p>
            <ol className="card px-5 py-1.5">
                {ROUTE_EFFORT.map((stage, index) => (
                    <li
                        key={stage.title}
                        className="grid grid-cols-[32px_minmax(0,1fr)] items-center gap-x-3.5 border-t py-3 first:border-t-0 sm:grid-cols-[32px_minmax(0,1fr)_auto]"
                    >
                        <span className="inline-flex size-8 items-center justify-center rounded-full border-[1.5px] border-input text-sm font-semibold text-muted-foreground">
                            {index + 1}
                        </span>
                        <span className="font-medium">{stage.title}</span>
                        <span className="col-start-2 text-sm text-muted-foreground sm:col-start-3">
                            {stage.effort}
                        </span>
                    </li>
                ))}
            </ol>
            <div className="flex flex-wrap gap-3">
                <Button size="lg" asChild>
                    <Link href={routeHref}>Empezar mi ruta</Link>
                </Button>
                <Button size="lg" variant="ghost" onClick={onRestart}>
                    Cambiar respuestas
                </Button>
            </div>
        </div>
    );
}
