const COOKIE = 'XSRF-TOKEN';

/** The CSRF token Laravel keeps in its cookie, for a request made with fetch (Inertia does it for its own). */
export function xsrfToken(cookies: string): string | null {
    const found = cookies
        .split(';')
        .map((cookie) => cookie.trim())
        .find((cookie) => cookie.startsWith(`${COOKIE}=`));

    return found === undefined
        ? null
        : decodeURIComponent(found.slice(COOKIE.length + 1));
}
