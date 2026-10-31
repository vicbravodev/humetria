import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

export function NavMain({
    label,
    items,
}: {
    label?: string;
    items: NavItem[];
}) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    // A page below an item keeps it lit (NOM-035 on an evaluation), unless the item opens its siblings (Panel).
    const isActive = (item: NavItem) => {
        const url = toUrl(item.href);

        return (
            isCurrentUrl(item.href) ||
            (isCurrentOrParentUrl(`${url}/`) &&
                !items.some((other) => toUrl(other.href).startsWith(`${url}/`)))
        );
    };

    return (
        <SidebarGroup className="py-0">
            {label && <SidebarGroupLabel>{label}</SidebarGroupLabel>}
            <SidebarMenu className="gap-0.5">
                {items.map((item) => (
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
                        {item.children !== undefined && isActive(item) && (
                            <SidebarMenuSub>
                                {item.children.map((child) => (
                                    <SidebarMenuSubItem key={child.title}>
                                        <SidebarMenuSubButton
                                            asChild
                                            isActive={isCurrentUrl(child.href)}
                                        >
                                            <Link href={child.href} prefetch>
                                                <span>{child.title}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                ))}
                            </SidebarMenuSub>
                        )}
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
