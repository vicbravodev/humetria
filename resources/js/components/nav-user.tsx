import { usePage } from '@inertiajs/react';
import { Settings } from 'lucide-react';
import Initials from '@/components/humetria/initials';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { staffLabel } from '@/lib/staff';

export function NavUser() {
    const { auth } = usePage().props;

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="h-14 font-normal data-[state=open]:bg-sidebar-accent"
                            data-test="sidebar-menu-button"
                        >
                            <Initials name={auth.user.name} />
                            <span className="grid min-w-0 flex-1 leading-tight">
                                <span className="truncate">
                                    {auth.user.name}
                                </span>
                                <span className="truncate text-caption text-muted-foreground">
                                    {staffLabel(auth) ?? auth.user.email}
                                </span>
                            </span>
                            <Settings
                                aria-hidden
                                className="ml-auto text-muted-foreground group-data-[collapsible=icon]:hidden"
                            />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="end"
                        side="top"
                    >
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
