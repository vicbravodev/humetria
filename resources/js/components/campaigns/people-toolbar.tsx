import Combobox from '@/components/humetria/combobox';
import SearchField from '@/components/humetria/search-field';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { peopleQuery } from '@/lib/campaigns';
import { serverSource } from '@/lib/server-options';
import { options as areaOptions } from '@/routes/tenant/areas';
import type { InstrumentProgress, PeopleFilters } from '@/types';

const ALL_GUIDES = 'all';
const ALL_AREAS = { value: '', label: 'Todas las áreas' };

/** Narrows the people of a campaign: text in the name, email or number; one area; one guide. Where they stand is the tabs'. */
export default function PeopleToolbar({
    tenantSlug,
    url,
    filters,
    instruments,
}: {
    tenantSlug: string;
    url: string;
    filters: PeopleFilters;
    instruments: InstrumentProgress[];
}) {
    const { text, type, filter } = useQueryFilters(url, filters, peopleQuery);

    return (
        <div
            role="search"
            className="grid gap-2.5 md:grid-cols-[minmax(0,420px)_13rem_13rem]"
        >
            <SearchField
                label="Buscar por nombre, correo o número"
                value={text}
                onChange={type}
            />
            <Combobox
                id="filter-area"
                name="area"
                source={serverSource(areaOptions.url(tenantSlug), [ALL_AREAS])}
                defaultValue={filters.area ?? ALL_AREAS}
                placeholder="Área"
                onChange={(option) =>
                    filter({ area: option.value === '' ? null : option })
                }
            />
            <Select
                value={filters.guide ?? ALL_GUIDES}
                onValueChange={(guide) =>
                    filter({ guide: guide === ALL_GUIDES ? null : guide })
                }
            >
                <SelectTrigger aria-label="Guía" className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL_GUIDES}>Todas las guías</SelectItem>
                    {instruments.map((instrument) => (
                        <SelectItem key={instrument.key} value={instrument.key}>
                            {instrument.title}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
