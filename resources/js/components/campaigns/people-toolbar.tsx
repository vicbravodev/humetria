import { Search } from 'lucide-react';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { peopleQuery } from '@/lib/campaigns';
import type { InstrumentProgress, PeopleFilters, StandingCount } from '@/types';

const ALL_GUIDES = 'all';

/** Narrows the people of a campaign: text in the name, email or number; where they stand; one guide. */
export default function PeopleToolbar({
    url,
    filters,
    standings,
    instruments,
}: {
    url: string;
    filters: PeopleFilters;
    standings: StandingCount[];
    instruments: InstrumentProgress[];
}) {
    const { text, type, filter } = useQueryFilters(url, filters, peopleQuery);

    return (
        <div
            role="search"
            className="grid gap-3 md:grid-cols-[1fr_14rem_12rem]"
        >
            <div className="relative">
                <Search
                    aria-hidden
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    type="search"
                    aria-label="Buscar por nombre, correo o número"
                    placeholder="Buscar por nombre, correo o número"
                    value={text}
                    onChange={(event) => type(event.target.value)}
                    className="pl-9"
                />
            </div>
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
        </div>
    );
}
