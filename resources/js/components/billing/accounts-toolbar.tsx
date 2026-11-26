import FilterChip from '@/components/billing/filter-chip';
import CountTabs from '@/components/humetria/count-tabs';
import SearchField from '@/components/humetria/search-field';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { accountsQuery, ageOf, kindOf } from '@/lib/billing';
import { BUCKET_LABELS } from '@/lib/billing-aging';
import { index } from '@/routes/billing/accounts';
import type { AccountFilters, AccountView } from '@/types';

const VIEWS: { value: AccountView; label: string }[] = [
    { value: 'all', label: 'Todas' },
    { value: 'receivable', label: 'Por cobrar' },
    { value: 'overdue', label: 'Vencidas' },
    { value: 'direct', label: 'Directas' },
    { value: 'consultant', label: 'Consultores' },
];

const TYPES = [
    { value: 'direct', label: 'Directas' },
    { value: 'consultant', label: 'Consultores' },
];

const AGES = Object.entries(BUCKET_LABELS).map(([value, label]) => ({
    value,
    label,
}));

/**
 * Narrows the accounts (tablero Cuentas y cobranza): one view at a time with how many each holds, then text in their name,
 * RFC or contact, their kind, how old their oldest balance is, and the month the figure of what came in reads.
 */
export default function AccountsToolbar({
    filters,
    counts,
    months,
}: {
    filters: AccountFilters;
    counts: Record<AccountView, number>;
    months: { value: string; label: string }[];
}) {
    const { text, type, filter } = useQueryFilters(
        index.url(),
        filters,
        accountsQuery,
    );

    return (
        <div className="grid gap-4">
            <CountTabs
                label="Vista de cuentas"
                tabs={VIEWS.map((view) => ({
                    label: view.label,
                    count: counts[view.value],
                    href: index.url({
                        query: accountsQuery({ ...filters, kind: view.value }),
                    }),
                    active: filters.kind === view.value,
                }))}
            />
            <div role="search" className="flex flex-wrap items-center gap-3">
                <SearchField
                    label="Buscar por razón social, RFC o contacto"
                    value={text}
                    onChange={type}
                    className="min-w-56 flex-1"
                />
                <FilterChip
                    name="Tipo"
                    idle="Tipo: todas"
                    any="Todas"
                    value={filters.type}
                    options={TYPES}
                    onChange={(value) => filter({ type: kindOf(value) })}
                />
                <FilterChip
                    name="Antigüedad"
                    any="Cualquiera"
                    value={filters.age}
                    options={AGES}
                    onChange={(value) => filter({ age: ageOf(value) })}
                />
                <FilterChip
                    name="Mes"
                    idle={`Mes: ${months[0]?.label ?? ''}`}
                    any={months[0]?.label ?? ''}
                    value={filters.month}
                    options={months.slice(1)}
                    onChange={(month) => filter({ month })}
                />
            </div>
        </div>
    );
}
