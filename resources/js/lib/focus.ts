/**
 * A ref that focuses its element once, as it mounts (a new survey screen), so a screen reader announces it. The page
 * stays where the code put it. Defined once here: an inline ref would run, and steal the focus, on every render.
 */
export function focusOnMount(element: Pick<HTMLElement, 'focus'> | null): void {
    element?.focus({ preventScroll: true });
}

type Revealable = Pick<HTMLElement, 'focus' | 'scrollIntoView'>;

/**
 * After a failed save, the first field with an error (or the error itself) in the middle of the screen, focused: on a phone the
 * button that sent it stays at the bottom while the error may be far up or down the page.
 */
export function revealFirstError(root: {
    querySelector: (selector: string) => Revealable | null;
}): void {
    const first = root.querySelector('[aria-invalid="true"], [role="alert"]');

    first?.scrollIntoView({ block: 'center' });
    first?.focus({ preventScroll: true });
}

/** Runs once the errors just set have been drawn: two frames, the second after React commits. */
export function afterRender(run: () => void): void {
    requestAnimationFrame(() => requestAnimationFrame(run));
}
