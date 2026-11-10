import { Head } from '@inertiajs/react';
import { Network } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import OrgChartBreadcrumbs from '@/components/organization/org-chart-breadcrumbs';
import OrgChartEmpty from '@/components/organization/org-chart-empty';
import OrgChartToolbar from '@/components/organization/org-chart-toolbar';
import OrgIndicators from '@/components/organization/org-indicators';
import OrgTree from '@/components/organization/org-tree';
import { orgChartHeadline, orgChartQuery } from '@/lib/org-chart/labels';
import { chartView } from '@/lib/org-chart/tree-state';
import type {
    OrgChartFilters,
    OrgIndicators as Indicators,
    OrgNode,
    Paginated,
} from '@/types';

export default function OrgChart({
    roots,
    chain,
    focus,
    filters,
    total_people,
    with_manager,
    indicators,
    can_manage,
}: {
    roots: Paginated<OrgNode>;
    chain: OrgNode[];
    focus: { id: string; children: Paginated<OrgNode> } | null;
    filters: OrgChartFilters;
    total_people: number;
    with_manager: number;
    indicators: Indicators;
    can_manage: boolean;
}) {
    const view = chartView(roots, chain, focus);

    return (
        <>
            <Head title="Organigrama" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Organigrama"
                    description={orgChartHeadline(total_people, with_manager)}
                />
                <OrgChartToolbar filters={filters} person={chain.at(-1)} />
                {with_manager === 0 ? (
                    <OrgChartEmpty canManage={can_manage} />
                ) : (
                    <OrgIndicators indicators={indicators} />
                )}
                {focus !== null && (
                    <OrgChartBreadcrumbs chain={chain} filters={filters} />
                )}
                {view.nodes.length === 0 ? (
                    total_people > 0 && (
                        <EmptyState icon={Network} title="Nadie coincide">
                            Cambia el centro de trabajo o el área para ver a más
                            personas.
                        </EmptyState>
                    )
                ) : (
                    <OrgTree
                        // A new tree for each view the server answers: what was open belongs to the previous one.
                        key={JSON.stringify([
                            orgChartQuery(filters, view.focusId ?? undefined),
                            roots.current_page,
                        ])}
                        view={view}
                        filters={filters}
                    />
                )}
                {focus === null && (
                    <Pagination page={roots} label="Páginas del organigrama" />
                )}
            </div>
        </>
    );
}
