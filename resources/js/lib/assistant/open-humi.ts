/** The event a page sends to open Humi (rediseño v2, the «¿Una etapa no te queda clara?» card); the dock listens for it. */
export const OPEN_HUMI = 'humi:open';

/** Opens Humi from anywhere in the panel, with a question already written in its box when there is one (never sent). */
export function openHumi(draft?: string): void {
    window.dispatchEvent(
        new CustomEvent(OPEN_HUMI, { detail: { draft: draft ?? null } }),
    );
}

/** The draft Humi's box starts from after an open request: a request without one keeps the draft already open. */
export function draftAfterOpen(
    current: string | null,
    requested: string | null,
): string | null {
    return requested ?? current;
}
