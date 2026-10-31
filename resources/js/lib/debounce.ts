/**
 * Runs `run` once the calls stop for `delay` ms, with the last call's arguments: for searches that follow typing.
 * `cancel` drops the waiting call and says whether there was one.
 */
export function debounce<Args extends unknown[]>(
    run: (...args: Args) => void,
    delay: number,
) {
    let timer: ReturnType<typeof setTimeout> | undefined;

    return {
        call: (...args: Args) => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                timer = undefined;
                run(...args);
            }, delay);
        },
        cancel: (): boolean => {
            const waiting = timer !== undefined;

            clearTimeout(timer);
            timer = undefined;

            return waiting;
        },
    };
}
