import { useEffect } from 'react';
import type { RefObject } from 'react';

/** While `open`, Escape or a click outside `root` calls `close`: how a disclosure that floats over the page is left. */
export function useDismiss(
    open: boolean,
    root: RefObject<HTMLElement | null>,
    close: () => void,
): void {
    useEffect(() => {
        if (!open) {
            return;
        }

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                close();
            }
        };
        const onClick = (event: MouseEvent) => {
            if (
                event.target instanceof Node &&
                root.current?.contains(event.target) !== true
            ) {
                close();
            }
        };

        document.addEventListener('keydown', onKey);
        document.addEventListener('click', onClick);

        return () => {
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('click', onClick);
        };
    }, [open, root, close]);
}
