import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

/**
 * «Si hoy llegara una inspección» (boards V2-Ruta-N037 and V2-Ruta-Silla): what the center could show, and where it gets by
 * ending its stage. The page puts its progress bar inside.
 */
export default function RouteInspectionCard({
    ready,
    applicable,
    afterStage,
    stage,
    href,
    children,
}: {
    ready: number;
    applicable: number;
    afterStage: number;
    stage: number | null;
    href: string;
    children: ReactNode;
}) {
    return (
        <section
            aria-labelledby="inspection"
            className="flex flex-col gap-3 card p-[22px]"
        >
            <h2 id="inspection" className="text-sm font-semibold">
                Si hoy llegara una inspección
            </h2>
            <p className="text-lg">
                Podrías mostrar{' '}
                <b className="font-semibold">
                    {ready} de {applicable}
                </b>{' '}
                cosas que revisa el inspector.
            </p>
            {children}
            {stage !== null && afterStage > ready && (
                <p className="text-sm text-muted-foreground">
                    Al terminar la etapa {stage} subes a {afterStage} de{' '}
                    {applicable}.
                </p>
            )}
            <Link
                href={href}
                className="touch-hit self-start text-sm font-medium text-primary hover:underline"
            >
                Ver lo que te pediría
            </Link>
        </section>
    );
}
