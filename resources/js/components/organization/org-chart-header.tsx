import { Network, UserCheck, Users } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import OrgChartActions from '@/components/organization/org-chart-actions';
import OrgListActions from '@/components/organization/org-list-actions';
import OrgViewSwitch from '@/components/organization/org-view-switch';
import { factTexts } from '@/lib/org-chart/canvas-labels';

type Edit = { editing: boolean; onChange: (editing: boolean) => void };

/**
 * The top of the chart and of the list: «Organigrama» with its facts (people, levels, how many have a manager) and the
 * switch between the two; on the chart, the button that edits; on the list, the CSV in and out.
 */
export default function OrgChartHeader({
    counts,
    view,
    edit,
}: {
    counts: { people: number; levels: number; withManager: number };
    view: 'chart' | 'list';
    /** On the chart, whoever may edit gets the button; null for who only sees it. */
    edit: Edit | null;
}) {
    const [people, levels, withManager] = factTexts(counts);

    return (
        <PageHeader
            title="Organigrama"
            status={
                edit?.editing === true ? (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-accent px-2.5 py-0.5 text-sm font-medium text-accent-foreground">
                        <span
                            aria-hidden
                            className="size-1.5 rounded-full bg-primary"
                        />
                        Editando
                    </span>
                ) : undefined
            }
            facts={[
                { icon: Users, text: people },
                { icon: Network, text: levels },
                { icon: UserCheck, text: withManager },
            ]}
        >
            <OrgViewSwitch current={view} />
            {view === 'list' && <OrgListActions />}
            {view === 'chart' && edit !== null && (
                <OrgChartActions edit={edit} />
            )}
        </PageHeader>
    );
}
