import RouteTask from '@/components/humetria/route-task';
import type { RouteStage } from '@/lib/route-stages';

/**
 * The tasks of the stage the center is in (board V2-Ruta-Silla): its requirements, what is left first, each with its
 * button for who may act on it.
 */
export default function RouteTasks({ stage }: { stage: RouteStage }) {
    const open = stage.rows.filter((row) => row.state !== 'done');
    const firstOpen = open[0]?.numeral;

    return (
        <ul className="@container mt-2.5 overflow-hidden card">
            {[...open, ...stage.rows.filter((row) => !open.includes(row))].map(
                (row) => (
                    <RouteTask
                        key={row.numeral}
                        title={row.title}
                        detail={row.detail}
                        refs={row.numeral}
                        done={row.state === 'done'}
                        href={row.href}
                        action={row.action}
                        primary={row.numeral === firstOpen}
                    />
                ),
            )}
        </ul>
    );
}
