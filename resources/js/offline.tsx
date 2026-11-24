import { createRoot } from 'react-dom/client';
import AppErrorBoundary from '@/components/app-error-boundary';
import OfflineApp from '@/components/attendance/offline-app';
import { initializeTheme } from '@/hooks/use-appearance';
import { cspNonce } from '@/lib/csp-nonce';
import { killedIn, registerWorkerApp, revisionOf } from '@/lib/pwa/register';

/*
 * The entry of the shell the service worker keeps (`/me/{slug}/offline`, see resources/views/worker-access/offline.blade.php):
 * no Inertia and no data from the server but what the page's own attributes say. Nothing is imported on demand: every
 * file this needs is cached with the shell.
 */

// Radix dialogs inject their scroll-lock <style> with the nonce they find here (get-nonce).
globalThis.__webpack_nonce__ = cspNonce;

const root = document.getElementById('offline-app');

initializeTheme();
void registerWorkerApp({
    pathname: location.pathname,
    revision: revisionOf(document),
    killed: killedIn(document),
    container:
        'serviceWorker' in navigator ? navigator.serviceWorker : undefined,
    dev: import.meta.env.DEV,
    caches: 'caches' in window ? window.caches : undefined,
});

if (root instanceof HTMLElement) {
    const { company, homeUrl } = root.dataset;

    createRoot(root).render(
        <AppErrorBoundary>
            {company === undefined || homeUrl === undefined ? (
                <OfflineApp company="Humetría" homeUrl="/" />
            ) : (
                <OfflineApp company={company} homeUrl={homeUrl} />
            )}
        </AppErrorBoundary>,
    );
}
