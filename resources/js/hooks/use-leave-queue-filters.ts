import { useQueryFilters } from '@/hooks/use-query-filters';
import { queueQuery } from '@/lib/leave/queue';
import { index } from '@/routes/tenant/vacations/requests';
import type { LeaveQueueFilters } from '@/types';

/** The filters of RH's queue of requests: see useQueryFilters. */
export function useLeaveQueueFilters(
    tenantSlug: string,
    filters: LeaveQueueFilters,
) {
    return useQueryFilters(index.url(tenantSlug), filters, queueQuery);
}
