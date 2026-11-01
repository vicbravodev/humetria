import { router } from '@inertiajs/react';
import Combobox from '@/components/humetria/combobox';
import { serverSource } from '@/lib/server-options';
import { index } from '@/routes/tenant/telework/trainings';
import { options } from '@/routes/tenant/telework/work-centers';
import type { TrainingFilters } from '@/types';

const ALL_CENTERS = { value: '', label: 'Todos los centros' };

/** Narrows the trainings and the roster to one work center. */
export default function TrainingToolbar({
    tenantSlug,
    filters,
}: {
    tenantSlug: string;
    filters: TrainingFilters;
}) {
    return (
        <div className="sm:w-64">
            <Combobox
                id="filter-training-center"
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
