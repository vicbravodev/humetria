import { useQueryFilters } from '@/hooks/use-query-filters';
import { panelListQuery } from '@/lib/panel-list';
import type { PanelListFilters } from '@/lib/panel-list';

/** The search and the center of a list of the panel by person (accesses, schedules), within its tab: see useQueryFilters. */
export function usePanelListFilters(url: string, filters: PanelListFilters) {
    return useQueryFilters(url, filters, panelListQuery);
}
