import CountTabs from '@/components/humetria/count-tabs';
import { results } from '@/routes/tenant/climate';
import type { EngagementCut } from '@/types';

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
    return (
        <CountTabs
            label="Cortar los resultados por"
            tabs={[
                {
                    label: 'Toda la empresa',
                    href: results.url([tenantSlug, surveyId]),
                    active: selected === null,
                },
                ...cuts.map((cut) => ({
                    label: cut.label,
                    href: results.url([tenantSlug, surveyId], {
                        query: { cut: cut.value },
                    }),
                    active: selected === cut.value,
                })),
            ]}
        />
    );
}
