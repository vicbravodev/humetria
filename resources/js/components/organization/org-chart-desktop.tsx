import OrgChartHeader from '@/components/organization/org-chart-header';
import OrgChartWorkspace from '@/components/organization/org-chart-workspace';
import OrgHeadsNotice from '@/components/organization/org-heads-notice';
import OrgIntroNote from '@/components/organization/org-intro-note';
import { useOrgWorkspace } from '@/hooks/use-org-workspace';
import { headsOf } from '@/lib/org-chart/mobile';
import type { OrgChartPageProps } from '@/types';

/**
 * The org chart page on a wide screen: header, the note that explains it, the amber note when there is more than one
 * head, and the canvas, which turns into the editing workspace for whoever may edit.
 */
export default function OrgChartDesktop({
    page,
    company,
}: {
    page: OrgChartPageProps;
    company: string;
}) {
    const chain = page.chain;
    const ws = useOrgWorkspace({
        init: {
            roots: page.roots.data,
            firstLevel: page.first_level,
            chain,
            focusChildren: page.focus?.children ?? null,
        },
        company: { name: company, people: page.total_people },
        filters: page.filters,
    });
    const heads = headsOf(ws.tree.state).length;

    return (
        <>
            <OrgChartHeader
                view="chart"
                counts={{
                    people: page.total_people,
                    levels: page.indicators.levels,
                    withManager: page.with_manager,
                }}
                edit={
                    page.can_manage
                        ? {
                              editing: ws.editor.editing,
                              onChange: ws.editor.setEditing,
                          }
                        : null
                }
            />
            {!ws.editor.editing && <OrgIntroNote />}
            {(heads > 1 || page.without_manager > 0) && (
                <OrgHeadsNotice heads={heads} unplaced={page.without_manager} />
            )}
            <OrgChartWorkspace
                ws={ws}
                filters={page.filters}
                person={chain.at(-1)}
            />
        </>
    );
}
