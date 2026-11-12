import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { NavGroup } from '@/components/nav-group';
import { NavSubmenu } from '@/components/nav-submenu';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { activeChild } from '@/lib/app-navigation';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

export function NavMain({
    label,
    icon,
    items,
}: {
    label?: string;
    icon?: LucideIcon | undefined;
    items: NavItem[];
}) {
    const { currentUrl, isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const { state, isMobile } = useSidebar();
    // Folded to its icons, the sidebar has no room for a submenu: its parent goes straight to its first page.
    const folded = state === 'collapsed' && !isMobile;
    // A page below an item keeps it lit (NOM-035 on an evaluation), unless the item opens its siblings (Panel).
    const isActive = (item: NavItem) => {
        const url = toUrl(item.href);

        return (
            isCurrentUrl(item.href) ||
            (isCurrentOrParentUrl(`${url}/`) &&
                !items.some((other) => toUrl(other.href).startsWith(`${url}/`)))
        );
    };

    const menu = (
        <SidebarMenu className="gap-0.5">
            {items.map((item) => {
                if (item.children !== undefined && !folded) {
                    const lit = activeChild(item.children, currentUrl);

                    return (
                        // A new key when the page enters or leaves the entry, so it opens on arriving.
                        <NavSubmenu
                            key={`${item.title}-${lit !== undefined}`}
                            item={item}
                            pages={item.children}
                            lit={lit}
                        />
                    );
                }

                return (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isActive(item)}
                            tooltip={item.title}
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                        {item.badge !== undefined && item.badge > 0 && (
                            <SidebarMenuBadge>{item.badge}</SidebarMenuBadge>
                        )}
                    </SidebarMenuItem>
                );
            })}
        </SidebarMenu>
    );

    // A family of tools folds; folded to its icons the sidebar has no labels, so every tool stays at hand.
    if (label !== undefined && !folded) {
        const holdsPage = items.some(
            (item) =>
                isActive(item) ||
                (item.children !== undefined &&
                    activeChild(item.children, currentUrl) !== undefined),
        );

        return (
            // A new key when the page enters or leaves the family, so it opens on arriving.
            <NavGroup
                key={String(holdsPage)}
                label={label}
                icon={icon}
                badge={items.reduce((sum, { badge }) => sum + (badge ?? 0), 0)}
                holdsPage={holdsPage}
            >
                {menu}
            </NavGroup>
        );
    }

    return (
        <SidebarGroup className="py-0">
            {label && <SidebarGroupLabel>{label}</SidebarGroupLabel>}
            {menu}
        </SidebarGroup>
    );
}
