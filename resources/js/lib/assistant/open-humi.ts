/** The event a page sends to open Humi (rediseño v2, the «¿Una etapa no te queda clara?» card); the dock listens for it. */
export const OPEN_HUMI = 'humi:open';

/** Opens Humi from anywhere in the panel. */
export function openHumi(): void {
    window.dispatchEvent(new Event(OPEN_HUMI));
}
