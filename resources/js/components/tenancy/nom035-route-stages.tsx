import { Link } from '@inertiajs/react';
import RouteStage from '@/components/humetria/route-stage';
import Nom035RouteTasks from '@/components/tenancy/nom035-route-tasks';
import Nom035StageLine from '@/components/tenancy/nom035-stage-line';
import { stageDone } from '@/lib/route-stages';
import type { RouteStage as Stage } from '@/lib/route-stages';

/**
 * The six stages of the NOM-035 route on their line (board V2-Ruta-N035): done, folded with their link; the one the center
 * is in with its task; ahead, with where they stand; the last one leads to «Si hoy llegara una inspección».
 */
export default function Nom035RouteStages({
    stages,
    inspectionHref,
    className,
}: {
    stages: Stage[];
    inspectionHref: string;
    className: string;
}) {
    return (
        <ol aria-label="Etapas de la NOM-035" className={className}>
            {stages.map((stage) => (
                <RouteStage
                    key={stage.number}
                    stage={stage}
                    done={stageDone(stage)}
                >
                    {stage.state === 'current' && (
                        <Nom035RouteTasks stage={stage} />
                    )}
                    {stage.state === 'pending' &&
                        stage.rows.map((row) => (
                            <Nom035StageLine key={row.numeral} row={row} />
                        ))}
                    {stage.number === 6 && (
                        <Link
                            href={inspectionHref}
                            className="touch-hit self-start text-sm font-medium text-primary hover:underline"
                        >
                            Ver lo que te pediría una inspección
                        </Link>
                    )}
                </RouteStage>
            ))}
        </ol>
    );
}
