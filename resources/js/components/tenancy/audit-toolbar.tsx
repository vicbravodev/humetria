import DateRangePicker from '@/components/humetria/date-range-picker';
import SearchField from '@/components/humetria/search-field';
import AuditCompanyFilter from '@/components/tenancy/audit-company-filter';
import AuditFamilyFilter from '@/components/tenancy/audit-family-filter';
import AuditFilterChip from '@/components/tenancy/audit-filter-chip';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { auditQuery } from '@/lib/audit';
import { periodChip } from '@/lib/audit-view';
import { periodShortcuts } from '@/lib/date-range';
import { index } from '@/routes/audit';
import type { AuditFamily, AuditFilters } from '@/types';

/** Narrows the trail (tablero Bitácora): a search by person or company, and the days, the company and the type as chips. */
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
    const family =
        families.find(({ value }) => value === filters.family)?.label ?? null;

    return (
        <div role="search" className="flex flex-wrap items-center gap-3">
            <SearchField
                label="Buscar por persona o empresa"
                value={text}
                onChange={type}
                className="min-w-[220px] flex-1"
            />
            <AuditFilterChip
                label="Fechas"
                value={periodChip(filters.from, filters.to)}
                onClear={() => filter({ from: '', to: '' })}
            >
                <DateRangePicker
                    id="audit-period"
                    label="Periodo"
                    value={{ from: filters.from, to: filters.to }}
                    onChange={(period) => filter(period)}
                    shortcuts={periodShortcuts()}
                />
            </AuditFilterChip>
            <AuditFilterChip
                label="Empresa"
                value={company}
                onClear={() => filter({ company: '' })}
            >
                <AuditCompanyFilter
                    company={
                        company === null
                            ? null
                            : { id: filters.company, name: company }
                    }
                    onChange={(next) => filter({ company: next })}
                />
            </AuditFilterChip>
            <AuditFilterChip
                label="Tipo"
                value={family}
                onClear={() => filter({ family: '' })}
            >
                <AuditFamilyFilter
                    family={filters.family}
                    families={families}
                    onChange={(next) => filter({ family: next })}
                />
            </AuditFilterChip>
        </div>
    );
}
