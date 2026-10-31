import { Search } from 'lucide-react';
import { AppBreadcrumbs } from '@/components/app-breadcrumbs';
import { ThemeSwitch } from '@/components/theme-switch';
import { Button } from '@/components/ui/button';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { setCommandPaletteOpen } from '@/hooks/use-command-palette';
import { useScrolled } from '@/hooks/use-scrolled';
import type { BreadcrumbItem } from '@/types';

// The top of the page panel: fold or open the sidebar (⌘B), where the page sits, search (⌘K) and the theme.
export function AppSidebarHeader({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItem[];
}) {
    const { state, isMobile } = useSidebar();
    const scrolled = useScrolled();
    const hint = state === 'expanded' ? 'Contraer menú' : 'Expandir menú';

    return (
        <header
            data-slot="app-topbar"
            data-scrolled={scrolled}
            // Stays at the top while the page scrolls; a divider shows once content passes under it.
            className="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between gap-3 border-b border-transparent bg-background px-2 transition-[border-color] duration-200 ease-out data-[scrolled=true]:border-border md:rounded-t-xl md:px-4"
        >
            <div className="flex min-w-0 items-center gap-2">
                <Tooltip>
                    <TooltipTrigger asChild>
                        <SidebarTrigger className="shrink-0 text-muted-foreground hover:text-foreground" />
                    </TooltipTrigger>
                    <TooltipContent side="bottom" hidden={isMobile}>
                        {hint}
                        <kbd className="ml-2 font-sans text-background/70">
                            ⌘B
                        </kbd>
                    </TooltipContent>
                </Tooltip>
                <AppBreadcrumbs items={breadcrumbs} />
            </div>
            <div className="flex shrink-0 items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    aria-label="Buscar"
                    data-test="command-palette"
                    onClick={() => setCommandPaletteOpen(true)}
                    className="gap-2 px-2.5 font-normal text-muted-foreground hover:text-foreground sm:w-60 sm:justify-start"
                >
                    <Search className="size-[18px]" />
                    <span className="hidden flex-1 text-left sm:inline">
                        Buscar o ir a…
                    </span>
                    <kbd className="hidden rounded border bg-muted px-1.5 font-sans text-[13px] leading-[18px] sm:inline">
                        ⌘K
                    </kbd>
                </Button>
                <ThemeSwitch />
            </div>
        </header>
    );
}
