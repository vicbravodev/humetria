import { Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
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
import { useSidebar } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { mobileTabs } from '@/lib/mobile-tabs';

const ICONS: Record<
    'panel' | 'compliance' | 'complaints' | 'more',
    LucideIcon
> = {
    panel: House,
    compliance: ClipboardCheck,
    complaints: MessageSquareWarning,
    more: Menu,
};

const TAB =
    'flex min-h-14 flex-col items-center justify-center gap-0.5 text-caption font-medium text-muted-foreground focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring aria-[current=page]:text-primary data-[state=open]:text-primary';

/**
 * The phone's menu (board 40, V4-Menu-Roles): a bar at the bottom with Panel, Cumplimiento, Quejas and Más, as the role's
 * sidebar has them. Cumplimiento opens its standards; Más opens the whole menu.
 */
export function MobileTabBar() {
    const { auth, tenant, inCourse } = usePage().props;
    const { setOpenMobile } = useSidebar();
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const tabs = mobileTabs(tenant, auth.isSuperadmin, inCourse?.count);

    return (
        <nav
            aria-label="Menú principal"
            className="fixed inset-x-0 bottom-0 z-30 grid auto-cols-fr grid-flow-col border-t bg-card pb-[env(safe-area-inset-bottom)] md:hidden print:hidden"
        >
            {tabs.map((tab) => {
                const Icon = ICONS[tab.key];
                const content = (
                    <>
                        <Icon aria-hidden className="size-5" />
                        {tab.title}
                    </>
                );

                switch (tab.key) {
                    case 'compliance':
                        return (
                            <DropdownMenu key={tab.key}>
                                <DropdownMenuTrigger className={TAB}>
                                    {content}
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    side="top"
                                    align="center"
                                    className="min-w-48"
                                >
                                    {tab.items.map((item) => (
                                        <DropdownMenuItem
                                            key={item.title}
                                            asChild
                                        >
                                            <Link href={item.href}>
                                                {item.title}
                                            </Link>
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        );
                    case 'more':
                        return (
                            <button
                                key={tab.key}
                                type="button"
                                className={TAB}
                                onClick={() => setOpenMobile(true)}
                            >
                                {content}
                            </button>
                        );
                    default:
                        return (
                            <Link
                                key={tab.key}
                                href={tab.item.href}
                                aria-current={
                                    (tab.key === 'panel'
                                        ? isCurrentUrl
                                        : isCurrentOrParentUrl)(tab.item.href)
                                        ? 'page'
                                        : undefined
                                }
                                className={TAB}
                            >
                                {content}
                            </Link>
                        );
                }
            })}
        </nav>
    );
}
