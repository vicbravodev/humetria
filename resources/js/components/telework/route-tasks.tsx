import RouteTask from '@/components/telework/route-task';
import { stageOneTasks } from '@/lib/telework/route';
import type { RouteStage } from '@/lib/telework/route';

/**
 * The tasks of the stage the center is in (board V2-Ruta-N037): its rows of table 10.3, what is left first. In the first
 * stage, the policy alone and its supporting documents together.
 */
export default function RouteTasks({ stage }: { stage: RouteStage }) {
    const open = stage.rows.filter(
        (row) => row.state !== 'done' && row.state !== 'not_applicable',
    );
    const firstOpen = open[0]?.numeral;

    if (stage.number === 1) {
        const { policy, documents, ready } = stageOneTasks(stage.rows);
        const docsHref =
            documents.find((row) => row.href !== null)?.href ?? null;

        return (
            <ul className="@container mt-2.5 overflow-hidden card">
                {policy !== undefined && (
                    <RouteTask
                        title="Termina tu Política de Teletrabajo"
                        detail={policy.detail}
                        refs={`numeral ${policy.numeral} · cubre a todo el centro`}
                        done={policy.state === 'done'}
                        href={policy.href}
                        action={policy.action}
                        primary={policy.state !== 'done'}
                    />
                )}
                <RouteTask
                    title={`Prepara ${documents.length} documentos de apoyo`}
                    detail={`${ready} de ${documents.length} listos. Riesgos, implementación, mantenimiento de equipo, regreso a la oficina, violencia familiar y apoyo a comisiones.`}
                    refs={documents.map((row) => row.numeral).join(', ')}
                    done={ready === documents.length}
                    href={docsHref}
                    action={`Ver los ${documents.length}`}
                    primary={
                        policy?.state === 'done' && ready < documents.length
                    }
                />
            </ul>
        );
    }

    return (
        <ul className="@container mt-2.5 overflow-hidden card">
            {[...open, ...stage.rows.filter((row) => !open.includes(row))].map(
                (row) => (
                    <RouteTask
                        key={row.numeral}
                        title={row.title}
                        detail={row.detail}
                        refs={`numeral ${row.numeral}`}
                        done={
                            row.state === 'done' ||
                            row.state === 'not_applicable'
                        }
                        href={row.href}
                        action={row.action}
                        primary={row.numeral === firstOpen}
                    />
                ),
            )}
        </ul>
    );
}
