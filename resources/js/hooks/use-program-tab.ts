import { usePage } from '@inertiajs/react';
import { activeTab } from '@/lib/interventions';
import type { ProgramTab } from '@/lib/interventions';

/** The tab of the Programa the address asks for (`?tab=actions`); the rows without one. */
export function useProgramTab(): ProgramTab {
    const { url } = usePage();

    return activeTab(url.includes('?') ? url.slice(url.indexOf('?')) : '');
}
