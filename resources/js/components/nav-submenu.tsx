import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { NavItem } from '@/types';

/**
 * An entry with pages of its own (NOM-037), as the sidebar design draws every submenu: the parent opens and folds it,
 * with no fill of its own (its icon in primary, 600 and a chevron), and only the page's child is a pill. It starts open
 * on one of its pages.
 */
export function NavSubmenu({
    item,
    pages,
    lit,
}: {
    item: NavItem;
    pages: NavItem[];
    /** The child of the page, if the page is one of them or hangs from one. */
    lit: NavItem | undefined;
}) {
    return (
        <Collapsible asChild defaultOpen={lit !== undefined}>
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton className="data-[state=open]:font-semibold data-[state=open]:[&>svg:first-child]:text-sidebar-primary data-[state=open]:[&>svg:last-child]:rotate-90">
                        {item.icon && <item.icon />}
                        <span className="truncate">{item.title}</span>
                        {item.badge !== undefined && item.badge > 0 && (
                            <span
                                data-sidebar="menu-badge"
                                className="ml-auto rounded-full bg-sidebar-accent px-[7px] text-xs leading-5 font-medium tabular-nums"
                            >
                                {item.badge}
                            </span>
                        )}
                        <ChevronRight
                            className={cn(
                                'size-4! transition-transform duration-200 ease-out',
                                !(item.badge !== undefined && item.badge > 0) &&
                                    'ml-auto',
                            )}
                        />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent className="unfold">
                    <SidebarMenuSub>
                        {pages.map((child) => (
                            <SidebarMenuSubItem key={child.title}>
                                <SidebarMenuSubButton
                                    asChild
                                    isActive={child === lit}
                                >
                                    <Link
                                        href={child.href}
                                        prefetch
                                        aria-current={
                                            child === lit ? 'page' : undefined
                                        }
                                    >
                                        <span>{child.title}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}
