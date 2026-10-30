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
import { loadServerOptions } from '@/lib/server-options';
import { options as areaOptions } from '@/routes/tenant/areas';
import type { InstrumentProgress, PeopleFilters, StandingCount } from '@/types';

const ALL_GUIDES = 'all';
const ALL_AREAS = { value: '', label: 'Todas las áreas' };

/** Narrows the people of a campaign: text in the name, email or number; where they stand; one guide; one area. */
export default function PeopleToolbar({
    tenantSlug,
    url,
    filters,
    standings,
    instruments,
}: {
    tenantSlug: string;
    url: string;
    filters: PeopleFilters;
    standings: StandingCount[];
    instruments: InstrumentProgress[];
}) {
    const { text, type, filter } = useQueryFilters(url, filters, peopleQuery);

    return (
        <div
            role="search"
            className="grid gap-3 md:grid-cols-2 xl:grid-cols-[1fr_14rem_12rem_14rem]"
        >
            <SearchField
                label="Buscar por nombre, correo o número"
                value={text}
                onChange={type}
            />
            <Select
                value={filters.status}
                onValueChange={(status) => filter({ status })}
            >
                <SelectTrigger aria-label="Situación" className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {standings.map((standing) => (
                        <SelectItem key={standing.value} value={standing.value}>
                            {standing.label} ({standing.count})
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
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
            <Combobox
                id="filter-area"
                name="area"
                source={{
                    initial: [ALL_AREAS],
                    minLength: 1,
                    load: (query, signal) =>
                        loadServerOptions(
                            areaOptions.url(tenantSlug),
                            query,
                            signal,
                        ),
                }}
                defaultValue={filters.area ?? ALL_AREAS}
                placeholder="Área"
                onChange={(option) =>
                    filter({ area: option.value === '' ? null : option })
                }
            />
        </div>
    );
}
