import { Link } from '@inertiajs/react';
import ProgressBar from '@/components/humetria/progress-bar';

/** «Si hoy llegara una inspección» (board V2-Ruta-N037): what the center could show, and where it gets by ending its stage. */
export default function RouteInspectionCard({
    ready,
    applicable,
    afterStage,
    stage,
    href,
}: {
    ready: number;
    applicable: number;
    afterStage: number;
    stage: number | null;
    href: string;
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
            <ProgressBar
                value={ready}
                max={applicable}
                label={`${ready} de ${applicable} cosas que podrías mostrar`}
            />
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
