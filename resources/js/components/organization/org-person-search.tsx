import { router } from '@inertiajs/react';
import Combobox from '@/components/humetria/combobox';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { orgChartQuery } from '@/lib/org-chart/labels';
import { serverSource } from '@/lib/server-options';
import { index } from '@/routes/tenant/org-chart';
import { search } from '@/routes/tenant/org-chart/people';
import type { OrgChartFilters, OrgNode } from '@/types';

/** «Busca a una persona…»: searched on the server; the page opens again focused on whoever is chosen. */
export default function OrgPersonSearch({
    filters,
    person,
}: {
    filters: OrgChartFilters;
    /** The focused person, shown as chosen. */
    person: OrgNode | undefined;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <Combobox
            id="org-chart-person"
            name="focus"
            source={serverSource(search.url(tenantSlug))}
            defaultValue={person && { value: person.id, label: person.name }}
            placeholder="Busca a una persona…"
            onChange={(option) =>
                router.get(
                    index.url(tenantSlug),
                    orgChartQuery(filters, option.value),
                    { preserveScroll: true },
                )
            }
        />
    );
}
