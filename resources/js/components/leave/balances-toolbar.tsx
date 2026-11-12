import CountTabs from '@/components/humetria/count-tabs';
import SearchField from '@/components/humetria/search-field';
import { useLeaveBalanceFilters } from '@/hooks/use-leave-balance-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { balancesQuery } from '@/lib/leave/balances';
import { index } from '@/routes/tenant/vacations/balances';
import type { LeaveBalanceFilters, LeaveBalanceTab } from '@/types';

const TABS: { tab: LeaveBalanceTab; label: string; asksAction: boolean }[] = [
    { tab: 'statements', label: 'Constancia sin acuse', asksAction: true },
    { tab: 'missing', label: 'Sin fecha de ingreso', asksAction: true },
    { tab: 'all', label: 'Todos', asksAction: false },
    { tab: 'terminations', label: 'Bajas por finiquitar', asksAction: true },
];

/** Narrows the list of balances: a tab per thing RH has to chase with how many it holds, and a search by name. */
export default function BalancesToolbar({
    filters,
    counts,
}: {
    filters: LeaveBalanceFilters;
    counts: Record<LeaveBalanceTab, number>;
}) {
    const tenantSlug = useTenantSlug();
    const { text, type } = useLeaveBalanceFilters(tenantSlug, filters);

    return (
        <div className="flex flex-wrap items-center justify-between gap-4">
            <SearchField
                label="Buscar por nombre"
                value={text}
                onChange={type}
                className="w-full sm:max-w-[320px]"
            />
            <CountTabs
                label="Qué revisar de los saldos"
                byAction
                tabs={TABS.map(({ tab, label, asksAction }) => ({
                    label,
                    count: counts[tab],
                    needsAction: asksAction,
                    active: filters.tab === tab,
                    href: index.url(tenantSlug, {
                        query: balancesQuery({ ...filters, tab }),
                    }),
                }))}
            />
        </div>
    );
}
