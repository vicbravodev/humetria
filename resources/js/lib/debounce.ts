/**
 * Runs `run` once the calls stop for `delay` ms, with the last call's arguments: for searches that follow typing.
 */
export function debounce<Args extends unknown[]>(
    run: (...args: Args) => void,
    delay: number,
) {
    let timer: ReturnType<typeof setTimeout> | undefined;

    return {
        call: (...args: Args) => {
            clearTimeout(timer);
            timer = setTimeout(() => run(...args), delay);
        },
        cancel: () => clearTimeout(timer),
    };
}
