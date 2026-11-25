/** How long the camera may look without reading any code before the person is told nothing could be read. */
export const QUIET_MS = 8_000;

/**
 * A timer that says «nothing could be read» once `QUIET_MS` go by with no code in sight. `arm` starts the wait again (never two
 * at once) and `clear` ends it: reading anything at all, right or wrong, shows the reading works.
 */
export function quietWatch(onQuiet: () => void): {
    arm: () => void;
    clear: () => void;
} {
    let timer: ReturnType<typeof setTimeout> | undefined;
    const clear = () => {
        clearTimeout(timer);
        timer = undefined;
    };

    return {
        arm: () => {
            clear();
            timer = setTimeout(onQuiet, QUIET_MS);
        },
        clear,
    };
}
