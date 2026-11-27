import { useSyncExternalStore } from 'react';

// A dialog or a sheet of the page is open (Radix keeps their content in the DOM only while open). A popover is not one.
const OPEN = '[data-slot="dialog-content"], [data-slot="sheet-content"]';

function subscribe(changed: () => void): () => void {
    // Radix mounts its portals as children of the body: their coming and going is all there is to watch.
    const observer = new MutationObserver(changed);
    observer.observe(document.body, { childList: true });

    return () => observer.disconnect();
}

function snapshot(): boolean {
    return document.querySelector(OPEN) !== null;
}

/** Whether a dialog or a sheet is open on the page right now, so what floats above the page can step aside. */
export function useOpenDialog(): boolean {
    return useSyncExternalStore(subscribe, snapshot, () => false);
}
