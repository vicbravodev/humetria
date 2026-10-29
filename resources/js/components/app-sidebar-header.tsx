import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

// The top of the page panel: fold or open the sidebar (⌘B). Each page names itself below, in its PageHeader.
export function AppSidebarHeader() {
    const { state, isMobile } = useSidebar();
    const hint = state === 'expanded' ? 'Contraer menú' : 'Expandir menú';

    return (
        <header
            data-slot="app-topbar"
            className="flex h-14 shrink-0 items-center justify-between gap-2 px-2 md:px-4"
        >
            <Tooltip>
                <TooltipTrigger asChild>
                    <SidebarTrigger className="text-muted-foreground hover:text-foreground" />
                </TooltipTrigger>
                <TooltipContent side="bottom" hidden={isMobile}>
                    {hint}
                    <kbd className="ml-2 font-sans text-background/70">⌘B</kbd>
                </TooltipContent>
            </Tooltip>
        </header>
    );
}
