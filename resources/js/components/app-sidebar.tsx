import { Link, usePage } from '@inertiajs/react';
import { Building2, House, Inbox } from 'lucide-react';
import Logo from '@/components/humetria/logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as companies } from '@/routes/companies';
import { index as demoRequests } from '@/routes/demo-requests';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Panel',
        href: dashboard(),
        icon: House,
    },
];

// Platform tools for Humetría staff; the server answers 404 to anyone else anyway.
const superadminNavItems: NavItem[] = [
    {
        title: 'Empresas',
        href: companies(),
        icon: Building2,
    },
    {
        title: 'Solicitudes de demo',
        href: demoRequests(),
        icon: Inbox,
    },
];

export function AppSidebar() {
    const { isSuperadmin } = usePage().props.auth;

    return (
        <Sidebar collapsible="offcanvas">
            <SidebarHeader className="px-3 pt-4 pb-4">
                <Link
                    href={dashboard()}
                    prefetch
                    className="flex h-11 w-fit items-center rounded-md px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    <Logo className="h-7" />
                </Link>
            </SidebarHeader>

            <SidebarContent className="gap-4 px-1">
                <NavMain label="NOM-035" items={mainNavItems} />
                {isSuperadmin && (
                    <NavMain label="Plataforma" items={superadminNavItems} />
                )}
            </SidebarContent>

            <SidebarFooter className="mx-3 border-t border-sidebar-border px-0 py-4">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
