/**
 * A ref that focuses its element once, as it mounts (a new survey screen), so a screen reader announces it. The page
 * stays where the code put it. Defined once here: an inline ref would run, and steal the focus, on every render.
 */
export function focusOnMount(element: Pick<HTMLElement, 'focus'> | null): void {
    element?.focus({ preventScroll: true });
}
