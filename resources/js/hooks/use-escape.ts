import { useEffect } from 'react';

/** Calls `onEscape` when Esc is pressed anywhere on the page, while `active`. */
export function useEscape(active: boolean, onEscape: () => void): void {
    useEffect(() => {
        if (!active) {
            return;
        }

        const listen = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onEscape();
            }
        };

        window.addEventListener('keydown', listen);

        return () => window.removeEventListener('keydown', listen);
    }, [active, onEscape]);
}
