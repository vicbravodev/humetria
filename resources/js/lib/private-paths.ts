/**
 * The addresses that keep no trace of who comes: the confidential channel of complaints (/c/{code}) and the public page of the
 * workers (/workers/{token}). Nothing of the app may be written to the cookies there, not even a preference, because a
 * cookie is a thread that ties a visit to the next one.
 */
export function isPrivatePath(pathname: string): boolean {
    return (
        pathname === '/c' ||
        pathname.startsWith('/c/') ||
        pathname === '/workers' ||
        pathname.startsWith('/workers/')
    );
}
