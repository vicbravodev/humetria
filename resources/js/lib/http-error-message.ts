type ErrorResponse = {
    status: number;
    data: unknown;
};

const SESSION_EXPIRED =
    'Tu sesión expiró; recarga la página e inténtalo de nuevo.';

/**
 * What to tell the person when a form submit fails with an error they can act on:
 * the server's text for too many requests (429), or to reload when the session expired (419).
 * Null for any other error, so Inertia handles it as usual.
 */
export function httpErrorMessage(response: ErrorResponse): string | null {
    if (response.status === 419) {
        return SESSION_EXPIRED;
    }

    return response.status === 429 &&
        typeof response.data === 'string' &&
        response.data !== ''
        ? response.data
        : null;
}
