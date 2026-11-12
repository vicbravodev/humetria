import { useHttp } from '@inertiajs/react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { update } from '@/routes/tenant/org-chart/manager';

export type ManagerChange = { ok: true } | { ok: false; message: string };

const FAILED = 'No se pudo guardar el cambio. Inténtalo de nuevo.';

/**
 * Saves who a person reports to with a request of its own (not a page visit): the chart keeps its place, and the
 * answer is the rule's own message when the manager may not be it.
 */
export function useManagerChange() {
    const tenantSlug = useTenantSlug();
    const http = useHttp<{ manager_id: string }, unknown>({ manager_id: '' });

    return {
        processing: http.processing,
        /** `managerId` empty leaves the person with nobody above them. */
        change: (employee: string, managerId: string): Promise<ManagerChange> =>
            new Promise((resolve) => {
                http.transform(() => ({ manager_id: managerId }));
                http.put(update.url({ tenant: tenantSlug, employee }), {
                    onSuccess: () => resolve({ ok: true }),
                    onError: (errors) =>
                        resolve({
                            ok: false,
                            message: errors['manager_id'] ?? FAILED,
                        }),
                }).catch(() => resolve({ ok: false, message: FAILED }));
            }),
    };
}
