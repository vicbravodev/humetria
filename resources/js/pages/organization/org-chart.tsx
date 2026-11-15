import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import OrgChartDesktop from '@/components/organization/org-chart-desktop';
import OrgChartEmpty from '@/components/organization/org-chart-empty';
import OrgChartHeader from '@/components/organization/org-chart-header';
import OrgChartPhone from '@/components/organization/org-chart-phone';
import { useIsMobile } from '@/hooks/use-mobile';
import type {
    OrgChartFilters,
    OrgFirstLevel,
    OrgIndicators,
    OrgNode,
    Paginated,
} from '@/types';

/**
 * «Organigrama»: who reports to whom, drawn as a canvas on a wide screen and as one person at a time on a phone.
 * Whoever may edit moves people on the same screen; the list is the table that is also the accessible view.
 */
export default function OrgChart(page: {
    roots: Paginated<OrgNode>;
    /** The first page of the reports of each root, keyed by the root. */
    first_level: OrgFirstLevel;
    /** From the top down to the focused person, who comes last; empty without a focus. */
    chain: OrgNode[];
    focus: { id: string; children: Paginated<OrgNode> } | null;
    filters: OrgChartFilters;
    total_people: number;
    with_manager: number;
    without_manager: number;
    indicators: OrgIndicators;
    can_manage: boolean;
}) {
    const company = usePage().props.tenant?.trade_name ?? '';
    const phone = useIsMobile();

    // With a search or a filter the address names no sidebar page: say where the page sits.
    setLayoutProps({
        breadcrumbs: [
            { title: 'Ordena tu operación' },
            { title: 'Organigrama' },
        ],
    });

    return (
        <>
            <Head title="Organigrama" />
            <div className="flex flex-col gap-6 px-4 py-6 md:px-10 md:py-8">
                {page.with_manager === 0 ? (
                    <>
                        <OrgChartHeader
                            view="chart"
                            counts={{
                                people: page.total_people,
                                levels: page.indicators.levels,
                                withManager: page.with_manager,
                            }}
                            edit={null}
                        />
                        <OrgChartEmpty canManage={page.can_manage} />
                    </>
                ) : phone ? (
                    <OrgChartPhone page={page} company={company} />
                ) : (
                    <OrgChartDesktop page={page} company={company} />
                )}
            </div>
        </>
    );
}
