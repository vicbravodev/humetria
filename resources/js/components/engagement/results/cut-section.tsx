import CutPicker from '@/components/engagement/results/cut-picker';
import GroupTable from '@/components/engagement/results/group-table';
import HiddenGroupsNotice from '@/components/engagement/results/hidden-groups-notice';
import type { EngagementResults } from '@/types';

/** The results by group: pick one attribute, see the groups that passed the minimum and that others were left out. */
export default function CutSection({
    tenantSlug,
    results,
}: {
    tenantSlug: string;
    results: EngagementResults;
}) {
    const { cut, cuts, groups, hasHidden, survey } = results;
    const heading = cuts.find((item) => item.value === cut)?.label ?? 'Grupo';

    return (
        <section className="flex flex-col gap-4">
            <h2 className="text-lg font-semibold">Por grupo</h2>
            <CutPicker
                tenantSlug={tenantSlug}
                surveyId={survey.id}
                cuts={cuts}
                selected={cut}
            />
            {groups !== null &&
                (groups.length > 0 ? (
                    <GroupTable heading={heading} groups={groups} />
                ) : (
                    <p className="text-muted-foreground">
                        Ningún grupo de este corte tiene respuestas suficientes
                        para mostrarse.
                    </p>
                ))}
            {hasHidden && <HiddenGroupsNotice />}
        </section>
    );
}
