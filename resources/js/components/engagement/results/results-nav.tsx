import CountTabs from '@/components/humetria/count-tabs';
import type { CountTab } from '@/components/humetria/count-tabs';
import {
    useEngagementKind,
    useEngagementRoutes,
} from '@/hooks/use-engagement-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { comments } from '@/routes/tenant/climate';

export type ResultsView =
    | 'summary'
    | 'items'
    | 'comments'
    | 'managers'
    | 'commitments';

/**
 * The views of a closed survey as tabs (pantalla Clima · Resultados): Resumen, Por pregunta, Comentarios (climate
 * only, the pulse asks none), Por jefe and Compromisos with how many there are.
 */
export default function ResultsNav({
    surveyId,
    active,
    commitments,
}: {
    surveyId: string;
    active: ResultsView;
    /** How many commitments the survey has; not drawn when the page does not know. */
    commitments?: number | undefined;
}) {
    const tenantSlug = useTenantSlug();
    const routes = useEngagementRoutes();
    const climate = useEngagementKind() === 'climate';
    const at: [string, string] = [tenantSlug, surveyId];
    const tabs: (CountTab & { view: ResultsView })[] = [
        {
            view: 'summary',
            label: 'Resumen',
            href: routes.results.url(at),
            active: false,
        },
        {
            view: 'items',
            label: 'Por pregunta',
            href: routes.results.items.url(at),
            active: false,
        },
        {
            view: 'comments',
            label: 'Comentarios',
            href: comments.url(at),
            active: false,
        },
        {
            view: 'managers',
            label: 'Por jefe',
            href: routes.managers.url(at),
            active: false,
        },
        {
            view: 'commitments',
            label: 'Compromisos',
            count: commitments,
            href: routes.commitments.index.url(at),
            active: false,
        },
    ];

    return (
        <CountTabs
            label="Vistas de los resultados"
            tabs={tabs
                .filter((tab) => climate || tab.view !== 'comments')
                .map((tab) => ({ ...tab, active: tab.view === active }))}
        />
    );
}
