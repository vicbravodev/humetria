import { Link, usePage } from '@inertiajs/react';
import Logo from '@/components/humetria/logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { NextObligationCard } from '@/components/next-obligation-card';
import { TenantSwitcher } from '@/components/tenant-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { navSections } from '@/lib/nav-sections';
import { dashboard } from '@/routes';
import { dashboard as tenantDashboard } from '@/routes/tenant';

export function AppSidebar() {
    const { auth, tenant, inCourse } = usePage().props;
    const sections = navSections({
        tenant,
        isSuperadmin: auth.isSuperadmin,
        inCourse: inCourse?.count ?? 0,
    });

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
                    <span className="flex items-center gap-2.5 group-data-[collapsible=icon]:hidden">
                        <Logo className="h-7" />
                        {/* Humetría staff outside any company: the platform console (pantalla Consola-Empresas). */}
                        {auth.isSuperadmin && tenant === null && (
                            <span className="inline-flex h-[26px] items-center rounded-full bg-warning px-2.5 text-caption font-medium text-warning-foreground">
                                Consola
                            </span>
                        )}
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
                {/* The company's own pages go without a heading; each family of tools under its name. */}
                {sections.map((section) =>
                    section.key === 'main' ? (
                        <NavMain key={section.label} items={section.items} />
                    ) : (
                        <NavMain
                            key={section.label}
                            label={section.label}
                            items={section.items}
                        />
                    ),
                )}
            </SidebarContent>

            <NextObligationCard />
            <SidebarFooter className="mx-3 border-t border-sidebar-border px-0 py-4 group-data-[collapsible=icon]:mx-1.5">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
