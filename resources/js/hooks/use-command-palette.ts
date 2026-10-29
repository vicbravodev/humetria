import { useEffect, useSyncExternalStore } from 'react';

const listeners = new Set<() => void>();
let open = false;

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

/** Opens or closes the ⌘K palette from anywhere: the top bar button, the shortcut, a command. */
export function setCommandPaletteOpen(next: boolean): void {
    open = next;
    listeners.forEach((listener) => listener());
}

export function useCommandPaletteOpen(): boolean {
    return useSyncExternalStore(
        subscribe,
        () => open,
        () => false,
    );
}

/** ⌘K (Ctrl+K) opens the palette, or closes it when it is open. Mount once, with the palette. */
export function useCommandPaletteShortcut(): void {
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                event.key.toLowerCase() === 'k' &&
                (event.metaKey || event.ctrlKey)
            ) {
                event.preventDefault();
                setCommandPaletteOpen(!open);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);
}
