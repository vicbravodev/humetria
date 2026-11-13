import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { cspNonce } from '@/lib/csp-nonce';
import { initialPageName } from '@/lib/page-frame';
import { pageTransition } from '@/lib/page-transition';
import { frameFor, preloadFrame } from '@/layouts/frames';

// Radix dialogs inject their scroll-lock <style> with the nonce they find here (get-nonce).
globalThis.__webpack_nonce__ = cspNonce;

const appName = import.meta.env['VITE_APP_NAME'] || 'Humetría';

await preloadFrame(
    initialPageName(
        document.querySelector('script[data-page="app"]')?.textContent,
    ),
);

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: frameFor,
    strictMode: true,
    defaults: {
        visitOptions: (href, options) =>
            pageTransition(href, options, window.location.href),
    },
    ...(cspNonce === undefined ? {} : { nonce: cspNonce }),
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: 'var(--primary)',
    },
});

// Applies the saved theme before the first paint.
initializeTheme();
