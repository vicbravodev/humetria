import { Link } from '@inertiajs/react';
import { useIsUnion } from '@/hooks/use-is-union';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { ContractStatus, StatusKey } from '@/lib/compensation/start';
import { statusItems } from '@/lib/compensation/start';
import { cn } from '@/lib/utils';
import { index as benefits } from '@/routes/tenant/compensation/benefits';
import { index as overrides } from '@/routes/tenant/compensation/overrides';
import { index as tabulator } from '@/routes/tenant/compensation/tabulator';
import { index as employees } from '@/routes/tenant/employees';

/** The data of the contract as a menu (board V4-Comp-Datos): each with what it has or lacks, the open one marked. */
export default function ContractDataNav({
    status,
    current,
}: {
    status: ContractStatus;
    current: StatusKey;
}) {
    const tenantSlug = useTenantSlug();
    const union = useIsUnion();
    const hrefs = {
        tabulator: tabulator(tenantSlug),
        benefits: benefits(tenantSlug),
        overrides: overrides(tenantSlug),
        risk_premium: benefits(tenantSlug),
        roster: employees(tenantSlug),
    };

    return (
        <nav aria-label="Datos del contrato">
            <ul className="grid gap-1 max-lg:grid-cols-2 max-sm:grid-cols-1">
                {statusItems(status, union, true).map((item) => (
                    <li key={item.key}>
                        <Link
                            href={hrefs[item.key]}
                            aria-current={
                                item.key === current ? 'page' : undefined
                            }
                            className={cn(
                                'grid gap-0.5 rounded-lg border border-transparent px-3 py-2.5 transition-colors hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                item.key === current && 'border-border bg-card',
                            )}
                        >
                            <span className="font-medium">{item.label}</span>
                            <span
                                className={cn(
                                    'text-sm',
                                    item.problem
                                        ? 'text-risk-alto-fg'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {item.problem ? item.note : item.value}
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
