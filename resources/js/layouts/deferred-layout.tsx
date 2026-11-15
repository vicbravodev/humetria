import { Suspense } from 'react';
import type { ReactNode } from 'react';
import { appFrame, authFrame, settingsFrame } from '@/layouts/frame-modules';
import type { BreadcrumbItem } from '@/types';

/**
 * A frame fetched only when a page needs it: the survey and the portals, which have their own, never download the
 * panel's sidebar, dock and palette.
 */
export default function DeferredLayout({
    name,
    breadcrumbs,
    children,
}: {
    name: 'app' | 'auth' | 'settings';
    breadcrumbs?: BreadcrumbItem[] | undefined;
    children: ReactNode;
}) {
    switch (name) {
        case 'auth':
            return (
                <Suspense fallback={null}>
                    <authFrame.Frame>{children}</authFrame.Frame>
                </Suspense>
            );
        case 'settings':
            return (
                <Suspense fallback={null}>
                    <settingsFrame.Frame>{children}</settingsFrame.Frame>
                </Suspense>
            );
        default:
            return (
                <Suspense fallback={null}>
                    <appFrame.Frame breadcrumbs={breadcrumbs}>
                        {children}
                    </appFrame.Frame>
                </Suspense>
            );
    }
}
