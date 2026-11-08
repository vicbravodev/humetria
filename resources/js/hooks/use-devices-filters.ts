import { useQueryFilters } from '@/hooks/use-query-filters';
import { devicesQuery } from '@/lib/attendance/devices';
import type { DevicesFilters } from '@/lib/attendance/devices';
import { index } from '@/routes/tenant/attendance/devices';

/** The search and the center of the devices page, within its tab: see useQueryFilters. */
export function useDevicesFilters(tenantSlug: string, filters: DevicesFilters) {
    return useQueryFilters(index.url(tenantSlug), filters, devicesQuery);
}
