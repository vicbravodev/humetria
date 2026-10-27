import { SidebarTrigger } from '@/components/ui/sidebar';

// Only on mobile, to open the sidebar sheet: on desktop the sidebar is always there, and each page names itself in its PageHeader.
export function AppSidebarHeader() {
    return (
        <header className="flex h-14 shrink-0 items-center px-2 md:hidden">
            <SidebarTrigger />
        </header>
    );
}
