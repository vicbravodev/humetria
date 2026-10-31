import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { CommandPalette } from '@/components/command-palette';
import { ImpersonatedBlockNotice } from '@/components/impersonated-block-notice';
import { ImpersonationBanner } from '@/components/impersonation-banner';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell>
            <AppSidebar />
            <AppContent className="min-w-0 overflow-x-clip">
                {/* One sticky block, so the top bar stays under the banner instead of sliding beneath it. */}
                <div className="sticky top-0 z-20 md:overflow-clip md:rounded-t-xl print:hidden">
                    <ImpersonationBanner />
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                </div>
                {/* The part that cross-fades between pages (app.css, ::view-transition-*(page)). */}
                <div className="flex flex-1 flex-col [view-transition-name:page]">
                    {children}
                </div>
            </AppContent>
            <CommandPalette />
            <ImpersonatedBlockNotice />
        </AppShell>
    );
}
