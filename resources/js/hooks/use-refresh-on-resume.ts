import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { shouldRefreshOnResume } from '@/lib/pwa/resume';

/** Whether the person is typing in a field: coming back never takes the text from under their fingers. */
function isTyping(): boolean {
    const active = document.activeElement;

    return (
        active instanceof HTMLInputElement ||
        active instanceof HTMLTextAreaElement
    );
}

/**
 * An installed app is not reloaded by the browser when it comes back: the person would see the screen of hours ago. After a
 * while out of sight, coming back loads the page again with its data of now (see `shouldRefreshOnResume`).
 */
export function useRefreshOnResume(): void {
    useEffect(() => {
        let hiddenAt: number | null = null;

        const onChange = () => {
            if (document.visibilityState === 'hidden') {
                hiddenAt = Date.now();

                return;
            }

            if (shouldRefreshOnResume(hiddenAt, Date.now(), isTyping())) {
                router.visit(`${location.pathname}${location.search}`, {
                    replace: true,
                    preserveState: false,
                    preserveScroll: true,
                });
            }

            hiddenAt = null;
        };

        document.addEventListener('visibilitychange', onChange);

        return () => document.removeEventListener('visibilitychange', onChange);
    }, []);
}
