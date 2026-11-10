import { useQueryFilters } from '@/hooks/use-query-filters';
import { balancesQuery } from '@/lib/leave/balances';
import { index } from '@/routes/tenant/vacations/balances';
import type { LeaveBalanceFilters } from '@/types';

/** The filters of the list of balances: see useQueryFilters. */
export function useLeaveBalanceFilters(
    tenantSlug: string,
    filters: LeaveBalanceFilters,
) {
    return useQueryFilters(index.url(tenantSlug), filters, balancesQuery);
}
