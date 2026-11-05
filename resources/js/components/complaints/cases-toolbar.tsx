import Combobox from '@/components/humetria/combobox';
import SearchField from '@/components/humetria/search-field';
import { useCaseFilters } from '@/hooks/use-case-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { Option } from '@/lib/option-search';
import { serverSource } from '@/lib/server-options';
import { options } from '@/routes/tenant/complaints/centers';
import type { CaseFilters } from '@/types';

const ALL_CENTERS = { value: '', label: 'Todos los centros' };
const ALL_TYPES = { value: '', label: 'Todos los tipos' };

/** Narrows the inbox: by reference, by type and by work center. */
export default function CasesToolbar({
    filters,
    types,
}: {
    filters: CaseFilters;
    types: Option[];
}) {
    const tenantSlug = useTenantSlug();
    const { text, type, filter } = useCaseFilters(tenantSlug, filters);

    return (
        <div
            role="search"
            className="grid gap-3 sm:grid-cols-2 xl:flex xl:min-w-0 xl:items-center"
        >
            <SearchField
                label="Buscar por referencia"
                value={text}
                onChange={type}
                className="sm:col-span-2 xl:max-w-[320px] xl:min-w-0 xl:flex-1"
            />
            <div className="xl:w-52 xl:shrink-0">
                <Combobox
                    id="filter-case-type"
                    name="type"
                    source={{ options: [ALL_TYPES, ...types] }}
                    defaultValue={
                        types.find((option) => option.value === filters.type) ??
                        ALL_TYPES
                    }
                    placeholder="Tipo"
                    onChange={(option) =>
                        filter({
                            type: option.value === '' ? null : option.value,
                        })
                    }
                />
            </div>
            <div className="xl:w-52 xl:shrink-0">
                <Combobox
                    id="filter-case-center"
                    name="center"
                    source={serverSource(options.url(tenantSlug), [
                        ALL_CENTERS,
                    ])}
                    defaultValue={filters.center ?? ALL_CENTERS}
                    placeholder="Centro de trabajo"
                    onChange={(option) =>
                        filter({
                            center: option.value === '' ? null : option,
                        })
                    }
                />
            </div>
        </div>
    );
}
