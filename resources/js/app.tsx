import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { cspNonce } from '@/lib/csp-nonce';
import { isStandalone } from '@/lib/page-frame';
import { pageTransition } from '@/lib/page-transition';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

// Radix dialogs inject their scroll-lock <style> with the nonce they find here (get-nonce).
globalThis.__webpack_nonce__ = cspNonce;

const appName = import.meta.env['VITE_APP_NAME'] || 'Humetría';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        // The survey, the portals, shared reports and the error page have their own frame, outside the panel.
        if (isStandalone(name)) {
            return null;
        }

        switch (true) {
            case name.startsWith('auth/'):
            case name === 'tenancy/accept-invitation':
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
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
