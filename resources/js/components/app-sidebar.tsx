import { Link, usePage } from '@inertiajs/react';
import Logo from '@/components/humetria/logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TenantSwitcher } from '@/components/tenant-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import {
    complianceNavItems,
    mainNavItems,
    superadminNavItems,
} from '@/lib/app-navigation';
import { dashboard } from '@/routes';
import { dashboard as tenantDashboard } from '@/routes/tenant';

export function AppSidebar() {
    const { auth, tenant } = usePage().props;
    const mainItems = mainNavItems(tenant, auth.isSuperadmin);
    const complianceItems = complianceNavItems(tenant);

    return (
        <Sidebar variant="inset" collapsible="icon">
            <SidebarHeader className="px-3 pt-4 pb-4">
                <Link
                    href={
                        tenant === null
                            ? dashboard()
                            : tenantDashboard(tenant.slug)
                    }
                    prefetch
                    className="flex h-11 w-fit items-center rounded-md px-3 transition-[padding] duration-300 ease-drawer group-data-[collapsible=icon]:px-1.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    <span className="contents group-data-[collapsible=icon]:hidden">
                        <Logo className="h-7" />
                    </span>
                    <span className="hidden group-data-[collapsible=icon]:contents">
                        <Logo kind="symbol" className="h-7" />
                    </span>
                </Link>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <TenantSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-4 px-1">
                {mainItems.length > 0 && <NavMain items={mainItems} />}
                {complianceItems.length > 0 && (
                    <NavMain label="Cumplimiento" items={complianceItems} />
                )}
                {auth.isSuperadmin && (
                    <NavMain label="Plataforma" items={superadminNavItems} />
                )}
            </SidebarContent>

            <SidebarFooter className="mx-3 border-t border-sidebar-border px-0 py-4 group-data-[collapsible=icon]:mx-1.5">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
