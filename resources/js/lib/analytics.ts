const LEAD_KEY = 'analytics.lead_id';

/**
 * Reports the conversion of a demo request the server confirmed stored. Without Google loaded (no measurement id or no
 * consent: today) or without the id of a stored request it does nothing; the same id never reports twice, so a reload or
 * a "back" does not repeat it. The event carries no parameters: nothing about the person.
 */
export function trackLead(
    target: Pick<Window, 'gtag'>,
    storage: Pick<Storage, 'getItem' | 'setItem'>,
    id: string | undefined,
): void {
    if (
        target.gtag === undefined ||
        id === undefined ||
        id === '' ||
        storage.getItem(LEAD_KEY) === id
    ) {
        return;
    }

    target.gtag('event', 'generate_lead');
    storage.setItem(LEAD_KEY, id);
}
