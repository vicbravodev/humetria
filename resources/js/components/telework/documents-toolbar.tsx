import { Link, router } from '@inertiajs/react';
import Combobox from '@/components/humetria/combobox';
import { Button } from '@/components/ui/button';
import { serverSource } from '@/lib/server-options';
import { index } from '@/routes/tenant/telework/documents';
import { options } from '@/routes/tenant/telework/work-centers';
import type { DocumentFilters } from '@/types';

const VIEWS = [
    { versions: 'latest', label: 'Últimas versiones' },
    { versions: 'all', label: 'Historial' },
] as const;

const ALL_CENTERS = { value: '', label: 'Todos los centros' };

/** Narrows the documents to one work center, and switches between the latest versions and the whole history. */
export default function DocumentsToolbar({
    tenantSlug,
    filters,
}: {
    tenantSlug: string;
    filters: DocumentFilters;
}) {
    const query = (
        center: string | undefined,
        versions: DocumentFilters['versions'],
    ) => ({
        ...(center === undefined || center === '' ? {} : { center }),
        ...(versions === 'all' ? { versions } : {}),
    });
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="sm:w-64">
                <Combobox
                    id="filter-document-center"
                    name="center"
                    source={serverSource(options.url(tenantSlug), [
                        ALL_CENTERS,
                    ])}
                    defaultValue={filters.center ?? ALL_CENTERS}
                    placeholder="Centro de trabajo"
                    onChange={(option) =>
                        router.get(
                            index.url(tenantSlug),
                            query(option.value, filters.versions),
                            { preserveScroll: true, replace: true },
                        )
                    }
                />
            </div>
            <nav aria-label="Versiones" className="flex gap-2">
                {VIEWS.map((view) => (
                    <Button
                        key={view.versions}
                        variant={
                            filters.versions === view.versions
                                ? 'default'
                                : 'outline'
                        }
                        asChild
                    >
                        <Link
                            href={index.url(tenantSlug, {
                                query: query(
                                    filters.center?.value,
                                    view.versions,
                                ),
                            })}
                            preserveScroll
                            aria-current={
                                filters.versions === view.versions
                                    ? 'page'
                                    : undefined
                            }
                        >
                            {view.label}
                        </Link>
                    </Button>
                ))}
            </nav>
        </div>
    );
}
