/** The code a QR screen shows: the address a phone opens, its image, and the seconds until the next one. */
export type KioskCode = { url: string; qr: string; expiresIn: number };

const MAX_WAIT_MS = 30_000;

/** What the screen shows: its code, the first one on its way, or the lost connection. */
export type KioskView = 'code' | 'preparing' | 'offline';

/**
 * Without connection the screen shows no code at all, not even the one it still holds: a code on the wall that no longer
 * turns could be photographed and used to mark from elsewhere.
 */
export function kioskView(code: KioskCode | null, online: boolean): KioskView {
    if (!online) {
        return 'offline';
    }

    return code === null ? 'preparing' : 'code';
}

/** Whole seconds until a code of `durationMs` turns, `elapsedMs` after it came; never below 0, and 0 only once it turned. */
export function secondsLeft(durationMs: number, elapsedMs: number): number {
    return Math.max(0, Math.ceil((durationMs - elapsedMs) / 1000));
}

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

/** What the server answered: the current code, `gone` when the screen no longer exists (404), or null for anything else. */
export type KioskAnswer = KioskCode | 'gone' | null;

/** How long the screen waits for an answer: past it, the connection counts as lost. */
const ANSWER_WAIT_MS = 10_000;

/**
 * Asks for the current code. Never throws: a network error, an error status, a page that is not JSON (a captive portal or
 * a proxy answering 200), a body cut halfway and a request that never answers (it gives up after `waitMs`) all give null,
 * so the screen always retries and never keeps showing a code it could not renew.
 */
export async function fetchKioskCode(
    endpoint: string,
    fetcher: typeof fetch = fetch,
    waitMs: number = ANSWER_WAIT_MS,
): Promise<KioskAnswer> {
    let timer: ReturnType<typeof setTimeout> | undefined;
    const gaveUp = new Promise<null>((resolve) => {
        timer = setTimeout(() => resolve(null), waitMs);
    });
    const asked = (async (): Promise<KioskAnswer> => {
        try {
            const response = await fetcher(endpoint, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (response.status === 404) {
                return 'gone';
            }

            return response.ok ? parseKioskCode(await response.json()) : null;
        } catch {
            return null;
        }
    })();

    try {
        return await Promise.race([asked, gaveUp]);
    } finally {
        clearTimeout(timer);
    }
}
