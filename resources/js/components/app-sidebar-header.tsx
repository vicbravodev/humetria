import { ThemeSwitch } from '@/components/theme-switch';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useScrolled } from '@/hooks/use-scrolled';

// The top of the page panel: fold or open the sidebar (⌘B), and the theme. Each page names itself below, in its PageHeader.
export function AppSidebarHeader() {
    const { state, isMobile } = useSidebar();
    const scrolled = useScrolled();
    const hint = state === 'expanded' ? 'Contraer menú' : 'Expandir menú';

    return (
        <header
            data-slot="app-topbar"
            data-scrolled={scrolled}
            // Stays at the top while the page scrolls; a divider shows once content passes under it.
            className="sticky top-0 z-20 flex h-14 shrink-0 items-center justify-between gap-2 border-b border-transparent bg-background px-2 transition-[border-color] duration-200 ease-out data-[scrolled=true]:border-border md:rounded-t-xl md:px-4"
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
            <ThemeSwitch />
        </header>
    );
}
