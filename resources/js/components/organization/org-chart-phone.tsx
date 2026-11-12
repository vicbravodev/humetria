import OrgChartHeader from '@/components/organization/org-chart-header';
import OrgHeadsNotice from '@/components/organization/org-heads-notice';
import OrgIntroNote from '@/components/organization/org-intro-note';
import OrgMobileChart from '@/components/organization/org-mobile-chart';
import type { OrgChartPageProps } from '@/types';

/**
 * The org chart page on a phone: no canvas and no editing (the list is where a manager is assigned on a small screen):
 * the header, the notes, and one person at a time.
 */
export default function OrgChartPhone({
    page,
    company,
}: {
    page: OrgChartPageProps;
    company: string;
}) {
    const heads = page.roots.data.filter(
        (person) => person.direct_reports > 0,
    ).length;

    return (
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
            <OrgIntroNote />
            <OrgMobileChart
                init={{
                    roots: page.roots.data,
                    firstLevel: page.first_level,
                    chain: page.chain,
                    focusChildren: page.focus?.children ?? null,
                }}
                filters={page.filters}
                company={company}
                notice={
                    heads > 1 || page.without_manager > 0 ? (
                        <OrgHeadsNotice
                            heads={heads}
                            unplaced={page.without_manager}
                        />
                    ) : null
                }
            />
        </>
    );
}
