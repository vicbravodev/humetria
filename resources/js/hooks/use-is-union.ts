import { usePage } from '@inertiajs/react';

/** Whether the account in the URL is a union: it has no work centers and loads its people by the company's ID. */
export function useIsUnion(): boolean {
    return usePage().props.tenant?.type === 'union';
}
