import OrgTreeNode from '@/components/organization/org-tree-node';
import { useOrgTree } from '@/hooks/use-org-tree';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { orgChartQuery } from '@/lib/org-chart/labels';
import type { chartView } from '@/lib/org-chart/tree-state';
import { reports } from '@/routes/tenant/org-chart';
import type { OrgChartFilters } from '@/types';

/**
 * The org chart as a tree (the Tree View pattern of WAI-ARIA): the same nested list is the chart on a wide screen
 * and the outline on a phone. Each person's reports are asked from the server when they are opened, a page at a time.
 */
export default function OrgTree({
    view: { nodes: roots, initial, placement, focusId },
    filters,
}: {
    /** The people at the top, what is already open below them, and where they sit among all the roots. */
    view: ReturnType<typeof chartView>;
    filters: OrgChartFilters;
}) {
    const tenantSlug = useTenantSlug();
    const tree = useOrgTree({
        roots,
        initial,
        start: focusId ?? undefined,
        reportsUrl: (employee, page) =>
            reports.url(
                { tenant: tenantSlug, employee },
                {
                    query: {
                        ...orgChartQuery(filters),
                        page: String(page),
                    },
                },
            ),
    });
    const shown = {
        focusId,
        filtered: filters.work_center !== null || filters.area !== null,
    };

    return (
        <ul
            role="tree"
            aria-label="Organigrama"
            onKeyDown={tree.onKeyDown}
            className="org-tree flex flex-col gap-3"
        >
            {roots.map((node, index) => (
                <OrgTreeNode
                    key={node.id}
                    node={node}
                    position={{
                        level: 1,
                        index: placement.before + index,
                        size: placement.total,
                    }}
                    tree={tree}
                    view={shown}
                />
            ))}
        </ul>
    );
}
