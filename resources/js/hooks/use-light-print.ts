import { useEffect } from 'react';

/** Prints in the light theme whatever the screen shows: a report on paper is read on white. */
export function useLightPrint(): void {
    useEffect(() => {
        const root = document.documentElement;
        let dark = false;
        const before = (): void => {
            dark = root.classList.contains('dark');
            root.classList.remove('dark');
            root.style.colorScheme = 'light';
        };
        const after = (): void => {
            root.classList.toggle('dark', dark);
            root.style.colorScheme = dark ? 'dark' : 'light';
        };

        window.addEventListener('beforeprint', before);
        window.addEventListener('afterprint', after);

        return () => {
            window.removeEventListener('beforeprint', before);
            window.removeEventListener('afterprint', after);
        };
    }, []);
}
