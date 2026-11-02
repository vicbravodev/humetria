import { usePage } from '@inertiajs/react';

/** The slug of the company in the URL, for its routes; empty outside a company's pages. */
export function useTenantSlug(): string {
    return usePage().props.tenant?.slug ?? '';
}
