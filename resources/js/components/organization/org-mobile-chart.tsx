import { useState } from 'react';
import type { ReactNode } from 'react';
import OrgMiniPerson from '@/components/organization/org-mini-person';
import OrgMobileTeam from '@/components/organization/org-mobile-team';
import OrgMobileCrumbs from '@/components/organization/org-mobile-crumbs';
import OrgMobilePerson from '@/components/organization/org-mobile-person';
import OrgPanelFlow from '@/components/organization/org-panel-flow';
import OrgPersonSearch from '@/components/organization/org-person-search';
import { useChartTree } from '@/hooks/use-chart-tree';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { ChartInit } from '@/lib/org-chart/chart-state';
import { orgChartQuery } from '@/lib/org-chart/labels';
import { crumbsOf, headsOf, startAt } from '@/lib/org-chart/mobile';
import { panelOf } from '@/lib/org-chart/panel';
import { reports } from '@/routes/tenant/org-chart';
import type { OrgChartFilters, OrgNode } from '@/types';

/**
 * The chart on a phone: no canvas but one person at a time. The person is in the middle with their manager above and
 * their direct team below; a tap on either moves there, and the crumbs go back up. Whoever has nobody above is listed
 * when the company has several.
 */
export default function OrgMobileChart({
    init,
    filters,
    company,
    notice,
}: {
    init: ChartInit;
    filters: OrgChartFilters;
    company: string;
    /** What asks for attention above the chart, if anything. */
    notice: ReactNode;
}) {
    const tenantSlug = useTenantSlug();
    const tree = useChartTree({
        init,
        reportsUrl: (employee, page) =>
            reports.url(
                { tenant: tenantSlug, employee },
                { query: { ...orgChartQuery(filters), page: String(page) } },
            ),
    });
    const [current, setCurrent] = useState(
        startAt(tree.state, init.chain.at(-1)?.id ?? null),
    );
    const panel = current === null ? null : panelOf(tree.state, current);
    const go = (id: string | null) => {
        setCurrent(id);

        if (id !== null) {
            tree.open(id);
        }
    };
    const heads: OrgNode[] = headsOf(tree.state);

    return (
        <div className="flex flex-col gap-4">
            <OrgPersonSearch filters={filters} person={init.chain.at(-1)} />
            {notice}
            <OrgMobileCrumbs
                crumbs={crumbsOf(tree.state, current, company)}
                onGo={go}
            />
            {panel === null ? (
                <ul className="grid gap-2" aria-label="Quién está hasta arriba">
                    {heads.map((head) => (
                        <li key={head.id}>
                            <OrgMiniPerson node={head} onSelect={go} />
                        </li>
                    ))}
                </ul>
            ) : (
                <>
                    {panel.manager !== null && (
                        <section aria-label="Su jefe" className="grid gap-2">
                            <p className="text-caption font-semibold tracking-wide text-muted-foreground uppercase">
                                Su jefe
                            </p>
                            <OrgMiniPerson
                                node={panel.manager}
                                direction="up"
                                onSelect={go}
                            />
                        </section>
                    )}
                    <OrgMobilePerson node={panel.node} />
                    {panel.node.direct_reports > 0 && (
                        <OrgMobileTeam
                            panel={panel}
                            onGo={go}
                            onMore={() => tree.loadMore(panel.node.id)}
                        />
                    )}
                    <OrgPanelFlow />
                </>
            )}
        </div>
    );
}
