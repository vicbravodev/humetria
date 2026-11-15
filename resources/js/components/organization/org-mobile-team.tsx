import OrgMiniPerson from '@/components/organization/org-mini-person';
import { Button } from '@/components/ui/button';
import type { panelOf } from '@/lib/org-chart/panel';

type Panel = NonNullable<ReturnType<typeof panelOf>>;

/** «Su equipo directo» on a phone: a guide line down the left, each person a step forward; the next page when there is one. */
export default function OrgMobileTeam({
    panel,
    onGo,
    onMore,
}: {
    panel: Panel;
    onGo: (id: string) => void;
    onMore: () => void;
}) {
    const failed = panel.status === 'error';

    return (
        <section aria-label="Su equipo directo" className="grid gap-2">
            <h3 className="font-semibold">
                Su equipo directo ({panel.node.direct_reports})
            </h3>
            <ul className="ml-3 grid gap-2 border-l-2 pl-4">
                {panel.reports.map((report) => (
                    <li key={report.id}>
                        <OrgMiniPerson node={report} onSelect={onGo} />
                    </li>
                ))}
            </ul>
            {failed && (
                <p role="alert" className="text-sm text-muted-foreground">
                    No se pudo cargar a su equipo.
                </p>
            )}
            {(panel.more || failed) && (
                <Button variant="outline" onClick={onMore}>
                    {failed ? 'Reintentar' : 'Ver más personas'}
                </Button>
            )}
        </section>
    );
}
