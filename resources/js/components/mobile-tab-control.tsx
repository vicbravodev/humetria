import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    BriefcaseBusiness,
    ClipboardCheck,
    House,
    Menu,
    MessageSquareWarning,
} from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { MobileTab } from '@/lib/mobile-tabs';
import { cn } from '@/lib/utils';

const ICONS: Record<MobileTab['key'], LucideIcon> = {
    panel: House,
    compliance: ClipboardCheck,
    operation: BriefcaseBusiness,
    complaints: MessageSquareWarning,
    more: Menu,
};

const TAB =
    'relative flex min-h-14 w-full flex-col px-3 items-center justify-center gap-0.5 rounded-full text-caption font-medium transition-colors duration-150 ease-out focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring';

/** One tab: a link to its page, the standards' menu (Normas) or the button that opens the whole menu (Más). */
export function MobileTabControl({
    tab,
    current,
    openMenu,
}: {
    tab: MobileTab;
    current: boolean;
    openMenu: () => void;
}) {
    const Icon = ICONS[tab.key];
    const className = cn(
        TAB,
        current ? 'text-primary-foreground' : 'text-foreground',
    );
    const content = (
        <>
            <Icon aria-hidden className="size-5" />
            {tab.title}
        </>
    );

    switch (tab.key) {
        case 'compliance':
        case 'operation':
            return (
                <DropdownMenu>
                    <DropdownMenuTrigger className={className}>
                        {content}
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        side="top"
                        align="center"
                        className="min-w-48"
                    >
                        {tab.items.map((item) => (
                            <DropdownMenuItem key={item.title} asChild>
                                <Link href={item.href}>{item.title}</Link>
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        case 'more':
            return (
                <button type="button" className={className} onClick={openMenu}>
                    {content}
                </button>
            );
        default:
            return (
                <Link
                    href={tab.item.href}
                    aria-current={current ? 'page' : undefined}
                    className={className}
                >
                    {content}
                </Link>
            );
    }
}
