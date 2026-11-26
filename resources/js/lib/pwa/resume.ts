/** How long the app has to be out of sight before coming back to it loads what changed. */
export const RESUME_AFTER_MS = 90_000;

/**
 * Whether the person coming back to the installed app should see the page again with its fresh data: it was hidden long enough
 * (`hiddenAt`, in milliseconds; null when it never was) and they are not in the middle of typing.
 */
export function shouldRefreshOnResume(
    hiddenAt: number | null,
    now: number,
    typing: boolean,
): boolean {
    return hiddenAt !== null && !typing && now - hiddenAt >= RESUME_AFTER_MS;
}
