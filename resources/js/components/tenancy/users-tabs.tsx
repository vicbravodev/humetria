import CountTabs from '@/components/humetria/count-tabs';
import { usersQuery } from '@/lib/platform-users';
import { index } from '@/routes/platform-users';
import type {
    PlatformUserCounts,
    PlatformUserFilters,
    PlatformUserTab,
} from '@/types';

/** The views of «Equipo Humetría», each with how many the search finds in it; the search carries over. */
export default function UsersTabs({
    filters,
    counts,
}: {
    filters: PlatformUserFilters;
    counts: PlatformUserCounts;
}) {
    const tab = (view: PlatformUserTab) => ({
        active: filters.tab === view,
        href: index.url({ query: usersQuery({ ...filters, tab: view }) }),
    });

    return (
        <CountTabs
            label="Vistas del equipo"
            tabs={[
                { label: 'Equipo', count: counts.team, ...tab('team') },
                {
                    label: 'Personas con cuenta',
                    count: counts.people,
                    ...tab('people'),
                },
                { label: 'Permisos por rol', ...tab('permissions') },
            ]}
        />
    );
}
