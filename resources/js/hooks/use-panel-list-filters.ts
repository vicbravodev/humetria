import { useQueryFilters } from '@/hooks/use-query-filters';
import { panelListQuery } from '@/lib/attendance/devices';
import type { PanelListFilters } from '@/lib/attendance/devices';

/** The search and the center of a list of the registro (devices, schedules), within its tab: see useQueryFilters. */
export function usePanelListFilters(url: string, filters: PanelListFilters) {
    return useQueryFilters(url, filters, panelListQuery);
}
