/**
 * The addresses of the confidential channel of complaints (/c/{code}): nothing of the app may be written to the cookies there,
 * not even a preference, because a cookie is a thread that ties a visit to the next one.
 */
export function isPrivatePath(pathname: string): boolean {
    return pathname === '/c' || pathname.startsWith('/c/');
}
