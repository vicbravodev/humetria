import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { cspNonce } from '@/lib/csp-nonce';
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
        switch (true) {
            // The survey has its own frame (SurveyShell), outside the panel.
            case name === 'landing':
            case name === 'campaigns/survey':
            // A report opened from a temporary link: no panel, nobody signed in.
            case name === 'results/shared-report':
            // An error page stands alone: the address may not even be a page of the panel.
            case name === 'error':
                return null;
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

// This will set light / dark mode on load...
initializeTheme();
