import CountTabs from '@/components/humetria/count-tabs';
import type { EngagementCut } from '@/types';
import { useEngagementRoutes } from '@/hooks/use-engagement-routes';

/**
 * How to cut the results: the whole company or by one attribute. One at a time, always: crossing two would narrow the
 * group until it pointed at someone.
 */
export default function CutPicker({
    tenantSlug,
    surveyId,
    cuts,
    selected,
}: {
    tenantSlug: string;
    surveyId: string;
    cuts: EngagementCut[];
    selected: EngagementCut['value'] | null;
}) {
    const routes = useEngagementRoutes();

    return (
        <CountTabs
            label="Cortar los resultados por"
            tabs={[
                {
                    label: 'Toda la empresa',
                    href: routes.results.url([tenantSlug, surveyId]),
                    active: selected === null,
                },
                ...cuts.map((cut) => ({
                    label: cut.label,
                    href: routes.results.url([tenantSlug, surveyId], {
                        query: { cut: cut.value },
                    }),
                    active: selected === cut.value,
                })),
            ]}
        />
    );
}
