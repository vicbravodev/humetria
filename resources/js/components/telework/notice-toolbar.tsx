import { router } from '@inertiajs/react';
import Combobox from '@/components/humetria/combobox';
import { serverSource } from '@/lib/server-options';
import { index } from '@/routes/tenant/telework/notices';
import { options } from '@/routes/tenant/telework/work-centers';
import type { NoticeFilters } from '@/types';

const ALL_CENTERS = { value: '', label: 'Todos los centros' };

/** Narrows the notices to one work center (the Comisión only finds its own). */
export default function NoticeToolbar({
    tenantSlug,
    filters,
}: {
    tenantSlug: string;
    filters: NoticeFilters;
}) {
    return (
        <div className="sm:w-64">
            <Combobox
                id="filter-notice-center"
                name="center"
                source={serverSource(options.url(tenantSlug), [ALL_CENTERS])}
                defaultValue={filters.center ?? ALL_CENTERS}
                placeholder="Centro de trabajo"
                onChange={(option) =>
                    router.get(
                        index.url(tenantSlug),
                        option.value === '' ? {} : { center: option.value },
                        { preserveScroll: true, replace: true },
                    )
                }
            />
        </div>
    );
}
