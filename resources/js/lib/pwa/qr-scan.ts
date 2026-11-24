import type { QRCanvasResult } from 'qr/dom.js';

/** What a frame gave: the text of the first code that could be read, if any (the others in the list are discarded). */
export function firstText(
    read: QRCanvasResult | undefined,
): string | undefined {
    if (typeof read === 'string' || read === undefined) {
        return read;
    }

    return read.find((one) => typeof one === 'string');
}

/** The only characters of a code: the token of today and `{windowIndex}.{hmac}`; no `|`, which the canonical text reserves. */
const CODE = /^[A-Za-z0-9._-]{1,128}$/;

function parse(text: string): URL | null {
    try {
        return new URL(text);
    } catch {
        return null;
    }
}

/** The company and the code of an address of Marcar (`/me/{slug}/punch?q=…`) of this origin; null for anything else. */
function punchAddress(
    text: string,
    origin: string,
): { company: string; code: string } | null {
    const url = parse(text);
    const company =
        url?.origin === origin
            ? /^\/me\/([^/]+)\/punch$/.exec(url.pathname)?.[1]
            : undefined;
    const code = url?.searchParams.get('q') ?? '';

    return company !== undefined && CODE.test(code) ? { company, code } : null;
}

/**
 * The code the screen of a center shows as a QR: the address of Marcar of a company with the code in `q`. Of this company
 * it gives the code; of another company, `'other-company'`; anything else (another origin, the old `/a/{slug}/punch`, no
 * code, a code that cannot be one) is nothing. A code of another center of the same company passes: the server decides
 * whether it counts.
 */
export function punchCode(
    text: string,
    { origin, slug }: { origin: string; slug: string },
): { code: string } | 'other-company' | null {
    const address = punchAddress(text, origin);

    if (address === null) {
        return null;
    }

    return address.company === slug ? { code: address.code } : 'other-company';
}

/** Why the camera did not open: the person said no, there is none (or it is in use), or something else. */
export function cameraFailure(
    error: unknown,
): 'denied' | 'unavailable' | 'failed' {
    const name = error instanceof Error ? error.name : '';

    if (name === 'NotAllowedError') {
        return 'denied';
    }

    return name === 'NotFoundError' || name === 'NotReadableError'
        ? 'unavailable'
        : 'failed';
}
