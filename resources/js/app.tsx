import { createInertiaApp, resetLayoutProps, router } from '@inertiajs/react';
import AppErrorBoundary from '@/components/app-error-boundary';
import { documentTitle } from '@/lib/document-title';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import RecentProofDialog from '@/components/worker-access/recent-proof-dialog';
import { initializeTheme } from '@/hooks/use-appearance';
import {
    afterPageLoad,
    startAnalytics,
    type AnalyticsEngine,
} from '@/lib/analytics';
import { cspNonce } from '@/lib/csp-nonce';
import { deferResizeObserver } from '@/lib/defer-resize-observer';
import {
    reportError,
    setErrorContext,
    startErrorTracking,
    watchErrors,
} from '@/lib/error-tracking';
import { layoutResetter } from '@/lib/layout-reset';
import { initialPageName } from '@/lib/page-frame';
import { rememberData } from '@/lib/recording-mask';
import {
    followPage,
    startSessionRecording,
    type RecordingEngine,
} from '@/lib/session-recording';
import { pageTransition } from '@/lib/page-transition';
import { frameFor, preloadFrame } from '@/layouts/frames';

// Radix dialogs inject their scroll-lock <style> with the nonce they find here (get-nonce).
globalThis.__webpack_nonce__ = cspNonce;

const appName = import.meta.env['VITE_APP_NAME'] || 'Humetría';

/** The page the server rendered first (its Inertia object): where the recording decides whether to start. */
let firstPage: unknown = null;

// Node renders this file too (SSR) and has no document: the frame is only preloaded in the browser.
if (typeof document !== 'undefined') {
    // Before anything creates an observer: a callback that resizes what it observes is not a crash.
    deferResizeObserver(window);

    const initialPage = document.querySelector(
        'script[data-page="app"]',
    )?.textContent;

    // What an error report says about where it happened: the page and its request id, kept up to date on every visit.
    firstPage = initialPage ? JSON.parse(initialPage) : null;
    setErrorContext(firstPage);
    const arrives = layoutResetter(
        initialPageName(initialPage),
        resetLayoutProps,
    );
    router.on('navigate', (event) => {
        arrives(event.detail.page.component);
        setErrorContext(event.detail.page);
        void followPage(event.detail.page);
    });
    // A partial reload brings more data without a new page: the recording hides it too.
    router.on('success', (event) => rememberData(event.detail.page));

    await preloadFrame(initialPageName(initialPage));
}

void createInertiaApp({
    title: (title) => documentTitle(title, appName),
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

// The recording instance: PostHog's build with its recorder inside (never a script from outside), only in a company's panel.
async function loadRecorder(): Promise<RecordingEngine> {
    const { default: posthog } = await import('posthog-js/full/no-external');

    return posthog;
}

async function loadPostHog(): Promise<AnalyticsEngine> {
    const { default: posthog } = await import('posthog-js/no-external');

    return posthog;
}

// The landing's engine: with the replay switched on, the full build (the recorder and the speed are inside, never a script
// from outside), downloaded after the page loaded so it never delays what the visitor came to see; without it, the light one.
async function loadLandingPostHog(replay: boolean): Promise<AnalyticsEngine> {
    if (!replay) {
        return loadPostHog();
    }

    await afterPageLoad(window);
    const { default: posthog } = await import('posthog-js/full/no-external');

    return posthog;
}

// PostHog only where the document asks for it (App\Http\Analytics): the landing's measurement, or the error reports of
// any other page. A page without either block never downloads it. The build without external scripts keeps the policy
// of the page to its own origin plus the one that receives the events.
if (typeof document !== 'undefined') {
    void startAnalytics(document, loadLandingPostHog);
    void startErrorTracking(document, loadPostHog, window.location.origin).then(
        (started) => started && watchErrors(window, reportError),
    );
    void startSessionRecording(document, firstPage, {
        load: loadRecorder,
        origin: window.location.origin,
    });
}
