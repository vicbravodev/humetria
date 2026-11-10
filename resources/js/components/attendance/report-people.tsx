import ReportTable from '@/components/attendance/report-table';
import CountTabs from '@/components/humetria/count-tabs';
import SearchField from '@/components/humetria/search-field';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { reportQuery } from '@/lib/attendance/report';
import { report } from '@/routes/tenant/attendance';
import type {
    Paginated,
    ReportPerson,
    ReportShow,
    ReportSummary,
} from '@/types';

type Filters = {
    center: string;
    from: string;
    to: string;
    q: string;
    show: ReportShow;
};

/** The people of the period by name, 25 at a time, each with their days; all of them, those with flags or those unanswered. */
export default function ReportPeople({
    people,
    summary,
    filters,
}: {
    people: Paginated<ReportPerson>;
    summary: ReportSummary;
    filters: Filters;
}) {
    const tenantSlug = useTenantSlug();
    const url = report.url(tenantSlug);
    const { text, type } = useQueryFilters(url, filters, reportQuery);
    const tabs: { show: ReportShow; label: string; count: number }[] = [
        { show: 'all', label: 'Todas', count: summary.people },
        { show: 'flagged', label: 'Con avisos', count: summary.flaggedPeople },
        {
            show: 'unconfirmed',
            label: 'Sin confirmar',
            count: summary.unconfirmedPeople,
        },
    ];
    const address = (show: ReportShow, q: string) =>
        report.url(tenantSlug, {
            query: reportQuery({ ...filters, show, q }),
        });

    return (
        <section aria-labelledby="dias" className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h2 id="dias" className="text-xl leading-7 font-semibold">
                    Día por día
                </h2>
                <div className="flex w-full flex-wrap items-center gap-3 sm:w-auto print:hidden">
                    <CountTabs
                        label="Qué personas ver"
                        tabs={tabs.map((tab) => ({
                            label: tab.label,
                            count: tab.count,
                            href: address(tab.show, filters.q),
                            active: filters.show === tab.show,
                        }))}
                    />
                    <SearchField
                        label="Buscar por nombre"
                        value={text}
                        onChange={type}
                        className="w-full sm:w-60"
                    />
                </div>
            </div>
            <ReportTable
                people={people}
                show={filters.show}
                searching={filters.q !== ''}
                all={address('all', '')}
            />
        </section>
    );
}
