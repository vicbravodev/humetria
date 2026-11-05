import { Search } from 'lucide-react';
import CountTabs from '@/components/humetria/count-tabs';
import { Input } from '@/components/ui/input';
import { useBenefitFilters } from '@/hooks/use-benefit-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type {
    BenefitCounts,
    BenefitFilters,
} from '@/lib/compensation/benefits';
import { benefitQuery } from '@/lib/compensation/benefits';
import { index } from '@/routes/tenant/compensation/benefits';

/** Narrows the catalog: text in the name, and the active or archived benefits. */
export default function BenefitsToolbar({
    filters,
    counts,
}: {
    filters: BenefitFilters;
    counts: BenefitCounts;
}) {
    const tenantSlug = useTenantSlug();
    const { text, type } = useBenefitFilters(tenantSlug, filters);
    const tab = (status: BenefitFilters['status']) => ({
        count: counts[status],
        active: filters.status === status,
        href: index.url(tenantSlug, {
            query: benefitQuery({ ...filters, status }),
        }),
    });

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div role="search" className="relative sm:max-w-[400px] sm:flex-1">
                <Search
                    aria-hidden
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    type="search"
                    aria-label="Buscar una prestación"
                    placeholder="Buscar una prestación"
                    value={text}
                    onChange={(event) => type(event.target.value)}
                    className="pl-10"
                />
            </div>
            <div className="sm:ml-auto sm:shrink-0">
                <CountTabs
                    label="Estado de las prestaciones"
                    tabs={[
                        { label: 'Activas', ...tab('active') },
                        { label: 'Archivadas', ...tab('archived') },
                    ]}
                />
            </div>
        </div>
    );
}
