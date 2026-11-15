import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { startAnalytics } from '@/lib/analytics';
import { cspNonce } from '@/lib/csp-nonce';
import { initialPageName } from '@/lib/page-frame';
import { pageTransition } from '@/lib/page-transition';
import { frameFor, preloadFrame } from '@/layouts/frames';

// Radix dialogs inject their scroll-lock <style> with the nonce they find here (get-nonce).
globalThis.__webpack_nonce__ = cspNonce;

const appName = import.meta.env['VITE_APP_NAME'] || 'Humetría';

// Node renders this file too (SSR) and has no document: the frame is only preloaded in the browser.
if (typeof document !== 'undefined') {
    await preloadFrame(
        initialPageName(
            document.querySelector('script[data-page="app"]')?.textContent,
        ),
    );
}

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

// PostHog only where the landing's document asks for it (App\Http\Analytics): a page without the block never downloads it.
// The build without external scripts keeps the policy of the page to its own origin plus the one that receives the events.
if (typeof document !== 'undefined') {
    void startAnalytics(document, async () => {
        const { default: posthog } = await import('posthog-js/no-external');

        return posthog;
    });
}
