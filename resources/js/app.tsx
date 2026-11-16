import { createInertiaApp, router } from '@inertiajs/react';
import AppErrorBoundary from '@/components/app-error-boundary';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import RecentProofDialog from '@/components/worker-access/recent-proof-dialog';
import { initializeTheme } from '@/hooks/use-appearance';
import { startAnalytics, type AnalyticsEngine } from '@/lib/analytics';
import { ignoreBenignNotices } from '@/lib/benign-notices';
import { cspNonce } from '@/lib/csp-nonce';
import {
    reportError,
    setErrorContext,
    startErrorTracking,
    watchErrors,
} from '@/lib/error-tracking';
import { initialPageName } from '@/lib/page-frame';
import { pageTransition } from '@/lib/page-transition';
import { frameFor, preloadFrame } from '@/layouts/frames';

// Radix dialogs inject their scroll-lock <style> with the nonce they find here (get-nonce).
globalThis.__webpack_nonce__ = cspNonce;

const appName = import.meta.env['VITE_APP_NAME'] || 'Humetría';

// Node renders this file too (SSR) and has no document: the frame is only preloaded in the browser.
if (typeof document !== 'undefined') {
    // Before anything that reports errors, so a notice that is not one never reaches them.
    ignoreBenignNotices(window);

    const initialPage = document.querySelector(
        'script[data-page="app"]',
    )?.textContent;

    // What an error report says about where it happened: the page and its request id, kept up to date on every visit.
    setErrorContext(initialPage ? JSON.parse(initialPage) : null);
    router.on('navigate', (event) => setErrorContext(event.detail.page));

    await preloadFrame(initialPageName(initialPage));
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
                <AppErrorBoundary>{app}</AppErrorBoundary>
                <Toaster />
                <RecentProofDialog />
            </TooltipProvider>
        );
    },
    progress: {
        color: 'var(--primary)',
    },
});

// Applies the saved theme before the first paint.
initializeTheme();

async function loadPostHog(): Promise<AnalyticsEngine> {
    const { default: posthog } = await import('posthog-js/no-external');

    return posthog;
}

// PostHog only where the document asks for it (App\Http\Analytics): the landing's measurement, or the error reports of
// any other page. A page without either block never downloads it. The build without external scripts keeps the policy
// of the page to its own origin plus the one that receives the events.
if (typeof document !== 'undefined') {
    void startAnalytics(document, loadPostHog);
    void startErrorTracking(document, loadPostHog, window.location.origin).then(
        (started) => started && watchErrors(window, reportError),
    );
}
