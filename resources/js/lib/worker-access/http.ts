/** How a page of the person talks to the server with fetch: the browser's fetch and the CSRF token of its cookie. */
export type HttpDeps = {
    fetcher: typeof fetch;
    xsrf: string | null;
};

/** A JSON POST of the page's own origin, with its session and its CSRF token. */
export async function postJson(
    url: string,
    body: unknown,
    { fetcher, xsrf }: HttpDeps,
): Promise<Response> {
    return fetcher(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(xsrf === null ? {} : { 'X-XSRF-TOKEN': xsrf }),
        },
        body: JSON.stringify(body),
    });
}

/** A failed answer that is not one the page expects: the server broke (5xx) or something else went wrong on the way. */
export function answerFailure(status: number): 'server' | 'failed' {
    return status >= 500 ? 'server' : 'failed';
}

/** What the person reads when the server broke: it is ours, not their signal. */
export const SERVER_TROUBLE =
    'Algo salió mal de nuestro lado. Vuelva a intentarlo en unos minutos.';

/** The `redirect` of a JSON answer, or null when it has none. */
export async function redirectOf(response: Response): Promise<string | null> {
    const json: unknown = await response.json();

    return typeof json === 'object' &&
        json !== null &&
        'redirect' in json &&
        typeof json.redirect === 'string'
        ? json.redirect
        : null;
}

/** Why the device did not sign or create a key: the person closed the prompt (or did not verify), or something else. */
export function promptFailure(error: unknown): 'cancelled' | 'failed' {
    return error instanceof DOMException && error.name === 'NotAllowedError'
        ? 'cancelled'
        : 'failed';
}
