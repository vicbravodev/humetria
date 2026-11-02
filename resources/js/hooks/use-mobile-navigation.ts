import { useCallback } from 'react';

export type CleanupFn = () => void;

export function useMobileNavigation(): CleanupFn {
    return useCallback(() => {
        // A menu closed by navigating can leave the body without pointer events: give them back.
        document.body.style.removeProperty('pointer-events');
    }, []);
}
