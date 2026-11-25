import DateRangePicker from '@/components/humetria/date-range-picker';
import SearchField from '@/components/humetria/search-field';
import AuditCompanyFilter from '@/components/tenancy/audit-company-filter';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { auditQuery } from '@/lib/audit';
import { periodShortcuts } from '@/lib/date-range';
import { index } from '@/routes/audit';
import type { AuditFamily, AuditFilters } from '@/types';

const ALL = 'all';

/** Narrows the trail: who acted, what kind of event, which company and which days. */
export default function AuditToolbar({
    filters,
    families,
    company,
}: {
    filters: AuditFilters;
    families: AuditFamily[];
    company: string | null;
}) {
    const { text, type, filter } = useQueryFilters(
        index.url(),
        filters,
        auditQuery,
    );

    return (
        <div role="search" className="flex flex-wrap items-end gap-3">
            <SearchField
                label="Buscar por correo de quien actuó"
                value={text}
                onChange={type}
                className="w-full md:w-[320px]"
            />
            <AuditCompanyFilter
                company={
                    company === null
                        ? null
                        : { id: filters.company, name: company }
                }
                onChange={(next) => filter({ company: next })}
            />
            <div className="grid w-full gap-1.5 md:w-60">
                <Label htmlFor="audit-family">Tipo de evento</Label>
                <Select
                    value={filters.family === '' ? ALL : filters.family}
                    onValueChange={(family) =>
                        filter({ family: family === ALL ? '' : family })
                    }
                >
                    <SelectTrigger id="audit-family" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>Todos los eventos</SelectItem>
                        {families.map((family) => (
                            <SelectItem key={family.value} value={family.value}>
                                {family.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <DateRangePicker
                id="audit-period"
                label="Periodo"
                value={{ from: filters.from, to: filters.to }}
                onChange={(period) => filter(period)}
                shortcuts={periodShortcuts()}
            />
        </div>
    );
}
