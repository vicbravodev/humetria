import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import HumiDock from '@/components/assistant/humi-dock';
import { CommandPalette } from '@/components/command-palette';
import { ImpersonatedBlockNotice } from '@/components/impersonated-block-notice';
import { ImpersonationBanner } from '@/components/impersonation-banner';
import { MobileTabBar } from '@/components/mobile-tab-bar';
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
                {/* The page's width: it fills the frame up to 1440 px and is centered beyond that (design-system.md, Layout). */}
                <div
                    data-slot="page-body"
                    className="mx-auto flex w-full max-w-[1440px] flex-1 flex-col pb-16 [view-transition-name:page] md:pb-0"
                >
                    {children}
                </div>
            </AppContent>
            <MobileTabBar />
            <HumiDock />
            <CommandPalette />
            <ImpersonatedBlockNotice />
        </AppShell>
    );
}
