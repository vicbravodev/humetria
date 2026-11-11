import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { orgChartQuery } from '@/lib/org-chart/labels';
import { index } from '@/routes/tenant/org-chart';
import type { OrgChartFilters, OrgNode } from '@/types';

/** The line of command of the focused person, from the whole chart down to them: each level focuses that person. */
export default function OrgChartBreadcrumbs({
    chain,
    filters,
}: {
    chain: OrgNode[];
    filters: OrgChartFilters;
}) {
    const tenantSlug = useTenantSlug();
    const href = (focus?: string) =>
        index.url(tenantSlug, { query: orgChartQuery(filters, focus) });

    return (
        <nav aria-label="Línea de mando" className="print:hidden">
            <ol className="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-meta text-muted-foreground">
                <li>
                    <Link
                        href={href()}
                        className="touch-hit rounded-sm underline-offset-4 hover:text-foreground hover:underline"
                    >
                        Organigrama
                    </Link>
                </li>
                {chain.map((node, position) => (
                    <li key={node.id} className="flex items-center gap-1.5">
                        <ChevronRight aria-hidden className="size-4" />
                        {position === chain.length - 1 ? (
                            <span
                                aria-current="page"
                                className="font-medium text-foreground"
                            >
                                {node.name}
                            </span>
                        ) : (
                            <Link
                                href={href(node.id)}
                                className="touch-hit rounded-sm underline-offset-4 hover:text-foreground hover:underline"
                            >
                                {node.name}
                            </Link>
                        )}
                    </li>
                ))}
            </ol>
        </nav>
    );
}
