import { Head, setLayoutProps } from '@inertiajs/react';
import { Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import OrgChartHeader from '@/components/organization/org-chart-header';
import OrgQualityEmpty from '@/components/organization/org-quality-empty';
import OrgQualityFilters from '@/components/organization/org-quality-filters';
import OrgQualityHelp from '@/components/organization/org-quality-help';
import OrgQualityTable from '@/components/organization/org-quality-table';
import OrgQualityTabs from '@/components/organization/org-quality-tabs';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { QualityFilters } from '@/lib/org-chart/quality';
import { index } from '@/routes/tenant/org-chart';
import type { Paginated, QualityPerson, ReportingIssue } from '@/types';

/**
 * «Lista» of the org chart: the people as a table, paged and searched on the server, with the views that ask for action
 * first (who has no manager, who leads more than 15, whose manager left) and each manager chosen on its row. It is the
 * view that needs no mouse and the one for keeping the data in order.
 */
export default function OrgChartQuality({
    issues,
    issue,
    filters,
    total_people,
    with_manager,
    levels,
    people,
    can_manage,
}: {
    issues: ReportingIssue[];
    issue: ReportingIssue['value'];
    filters: QualityFilters;
    total_people: number;
    with_manager: number;
    levels: number;
    people: Paginated<QualityPerson>;
    can_manage: boolean;
}) {
    const tenantSlug = useTenantSlug();

    // The query of the list keeps the sidebar from naming it: its family, the chart and then the list.
    setLayoutProps({
        breadcrumbs: [
            { title: 'Ordena tu operación' },
            { title: 'Organigrama', href: index(tenantSlug) },
            { title: 'Lista' },
        ],
    });

    const complete = issues.every(
        (view) => view.value === 'all' || !view.pending,
    );

    return (
        <>
            <Head title="Lista del organigrama" />
            <div className="flex flex-col gap-6 px-4 py-6 md:px-10 md:py-8">
                <OrgChartHeader
                    view="list"
                    counts={{
                        people: total_people,
                        levels,
                        withManager: with_manager,
                    }}
                    edit={null}
                />
                <OrgQualityHelp />
                {complete && <OrgQualityEmpty people={total_people} />}
                <OrgQualityTabs
                    issues={issues}
                    current={issue}
                    filters={filters}
                />
                <OrgQualityFilters filters={filters} issue={issue} />
                {people.data.length === 0 ? (
                    <EmptyState icon={Users} title="Nadie en esta vista">
                        Cambia de vista o quita la búsqueda: ahí está el resto
                        de las personas.
                    </EmptyState>
                ) : (
                    <OrgQualityTable
                        people={people.data}
                        canManage={can_manage}
                    />
                )}
                <Pagination page={people} label="Páginas de la lista" />
            </div>
        </>
    );
}
