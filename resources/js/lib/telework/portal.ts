import type { PortalPending } from '@/types';

/** Whether the portal has nothing to show: no checklist, photo, visit or document, pending or sent. */
export function nothingPending(pending: PortalPending): boolean {
    return Object.values(pending).every((items) => items.length === 0);
}
