import { createElement } from 'react';
import type { ReactNode } from 'react';
import { appFrame, authFrame, settingsFrame } from '@/layouts/frame-modules';
import DeferredLayout from '@/layouts/deferred-layout';
import { isStandalone } from '@/lib/page-frame';
import type { BreadcrumbItem } from '@/types';

type FrameProps = {
    breadcrumbs?: BreadcrumbItem[] | undefined;
    children: ReactNode;
};

function frame(name: 'app' | 'auth' | 'settings') {
    return (props: FrameProps) =>
        // `name` goes last: Inertia's shared prop of the same name must not pick the frame.
        createElement(DeferredLayout, { ...props, name });
}

export const AppFrame = frame('app');
export const AuthFrame = frame('auth');
export const SettingsFrame = frame('settings');

/** The frame a page renders in; the survey, the portals and the error page have their own, outside the panel. */
export function frameFor(name: string) {
    if (isStandalone(name)) {
        return null;
    }

    switch (true) {
        case name.startsWith('auth/'):
        case name === 'tenancy/accept-invitation':
            return AuthFrame;
        case name.startsWith('settings/'):
            return [AppFrame, SettingsFrame];
        default:
            return AppFrame;
    }
}

/** Fetches the frame of the page the visit opened with, so it renders with its frame and not an instant after. */
export async function preloadFrame(name: string): Promise<void> {
    switch (true) {
        case isStandalone(name):
            return;
        case name.startsWith('auth/'):
        case name === 'tenancy/accept-invitation':
            await authFrame.preload();
            return;
        case name.startsWith('settings/'):
            await Promise.all([appFrame.preload(), settingsFrame.preload()]);
            return;
        default:
            await appFrame.preload();
    }
}
