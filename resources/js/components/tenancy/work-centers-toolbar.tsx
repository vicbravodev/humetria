import CountTabs from '@/components/humetria/count-tabs';
import SearchField from '@/components/humetria/search-field';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/work-centers';
import type { WorkCentersView } from '@/types';

/**
 * The top of the work centers: the search (searched in the server) at the left, the active and archived tabs at the
 * right. Each shows only when it has something to do.
 */
export default function WorkCentersToolbar({
    view,
    search,
    counts,
}: {
    view: WorkCentersView;
    search: string;
    counts: Record<WorkCentersView, number>;
}) {
    const tenantSlug = useTenantSlug();
    const { text, type } = useQueryFilters(
        index.url(tenantSlug),
        { q: search, archived: view === 'archived' ? '1' : '' },
        ({ q, archived }) => ({
            ...(q === '' ? {} : { q }),
            ...(archived === '' ? {} : { archived }),
        }),
    );
    // Only when there is something to search, or a search is on.
    const searchable = counts[view] > 0 || search !== '';
    // Only once there is something archived, or while looking at the archive.
    const tabbed = counts.archived > 0 || view === 'archived';

    if (!searchable && !tabbed) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-4">
            {searchable && (
                <SearchField
                    label="Buscar por nombre o municipio"
                    value={text}
                    onChange={type}
                    className="w-full sm:max-w-[320px]"
                />
            )}
            {tabbed && (
                <CountTabs
                    label="Vistas de centros de trabajo"
                    tabs={[
                        {
                            label: 'Activos',
                            count: counts.active,
                            href: index.url(tenantSlug),
                            active: view === 'active',
                        },
                        {
                            label: 'Archivados',
                            count: counts.archived,
                            href: index.url(tenantSlug, {
                                query: { archived: 1 },
                            }),
                            active: view === 'archived',
                        },
                    ]}
                />
            )}
        </div>
    );
}
