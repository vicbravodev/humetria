import { useEffect } from 'react';

/** Ctrl or Cmd + J anywhere in the panel, to open or close Humi. */
export function useHumiShortcut(toggle: () => void): void {
    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (
                (event.ctrlKey || event.metaKey) &&
                event.key.toLowerCase() === 'j'
            ) {
                event.preventDefault();
                toggle();
            }
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [toggle]);
}
