import RouteTask from '@/components/humetria/route-task';
import type { RouteStage } from '@/lib/route-stages';

/** The task of the stage the center is in (board V2-Ruta-N035): its requirement, with its button for whoever may act on it. */
export default function Nom035RouteTasks({ stage }: { stage: RouteStage }) {
    return (
        <ul className="@container mt-2.5 overflow-hidden card">
            {stage.rows.map((row) => (
                <RouteTask
                    key={row.numeral}
                    title={row.title}
                    detail={row.detail}
                    refs={`numeral ${row.numeral}`}
                    done={row.state === 'done'}
                    href={row.href}
                    action={row.action}
                    primary={row.state !== 'done'}
                />
            ))}
        </ul>
    );
}
