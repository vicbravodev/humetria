/** The code a QR screen shows: the address a phone opens, its image, and the seconds until the next one. */
export type KioskCode = { url: string; qr: string; expiresIn: number };

const MAX_WAIT_MS = 30_000;

/**
 * When to ask for the next code: half a second after the window turns, since asking before gives the same code. The code
 * on the screen stays valid 30 s past its window, so a person scanning at the turn still marks.
 */
export function refreshDelay(expiresIn: number): number {
    return Math.min(Math.max(expiresIn * 1000, 1_000), MAX_WAIT_MS) + 500;
}

/** After `failures` failed requests in a row: 1 s, 2 s, 4 s… up to 30 s. */
export function retryDelay(failures: number): number {
    return Math.min(1_000 * 2 ** (failures - 1), MAX_WAIT_MS);
}

/** The code in the server's answer, or null when the answer is not one (only an SVG data image is shown). */
export function parseKioskCode(body: unknown): KioskCode | null {
    if (
        typeof body !== 'object' ||
        body === null ||
        !('url' in body) ||
        !('qr' in body) ||
        !('expiresIn' in body)
    ) {
        return null;
    }

    const { url, qr, expiresIn } = body;

    return typeof url === 'string' &&
        typeof qr === 'string' &&
        qr.startsWith('data:image/svg+xml;base64,') &&
        typeof expiresIn === 'number'
        ? { url, qr, expiresIn }
        : null;
}
