import CountTabs from '@/components/humetria/count-tabs';
import SearchField from '@/components/humetria/search-field';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { accountsQuery } from '@/lib/billing';
import { index } from '@/routes/billing/accounts';
import type { AccountFilters, AccountView } from '@/types';

const VIEWS: { value: AccountView; label: string }[] = [
    { value: 'all', label: 'Todas' },
    { value: 'direct', label: 'Directas' },
    { value: 'consultant', label: 'Consultores' },
];

/** Narrows the accounts: text in their name, RFC or contact, and one kind at a time with how many each holds. */
export default function AccountsToolbar({
    filters,
    counts,
}: {
    filters: AccountFilters;
    counts: Record<AccountView, number>;
}) {
    const { text, type } = useQueryFilters(index.url(), filters, accountsQuery);

    return (
        <div role="search" className="flex flex-wrap items-center gap-3">
            <SearchField
                label="Buscar por nombre, RFC o contacto"
                value={text}
                onChange={type}
                className="w-full md:w-[380px]"
            />
            <CountTabs
                label="Tipo de cuenta"
                tabs={VIEWS.map((view) => ({
                    label: view.label,
                    count: counts[view.value],
                    href: index.url({
                        query: accountsQuery({ ...filters, kind: view.value }),
                    }),
                    active: filters.kind === view.value,
                }))}
            />
        </div>
    );
}
