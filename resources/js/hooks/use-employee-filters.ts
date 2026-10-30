import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { debounce } from '@/lib/debounce';
import type { EmployeeFilters } from '@/lib/employees';
import { employeeQuery } from '@/lib/employees';
import { index } from '@/routes/tenant/employees';

/**
 * The employees list's filters, each one a visit to the same page with the new query. The text waits for typing to
 * pause (300 ms) so the server is asked once per search, not once per key.
 */
export function useEmployeeFilters(
    tenantSlug: string,
    filters: EmployeeFilters,
) {
    const [text, setText] = useState(filters.q);
    const search = useMemo(() => {
        const visit = (next: Partial<EmployeeFilters>) =>
            router.get(
                index.url(tenantSlug),
                employeeQuery({ ...filters, ...next }),
                { preserveState: true, preserveScroll: true, replace: true },
            );

        return { visit, typed: debounce((q: string) => visit({ q }), 300) };
    }, [tenantSlug, filters]);

    useEffect(() => search.typed.cancel, [search]);

    return {
        text,
        type: (q: string) => {
            setText(q);
            search.typed.call(q);
        },
        filter: (next: Partial<EmployeeFilters>) => search.visit(next),
    };
}
