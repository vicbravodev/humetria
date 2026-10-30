import { Link, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { TenantName } from '@/components/tenant-name';
import { SidebarMenuButton } from '@/components/ui/sidebar';
import { dashboard as home } from '@/routes';
import { dashboard } from '@/routes/tenant';

/** The company the sidebar is working in; with more than one, it is also the way to move to another. */
export function TenantSwitcher() {
    const { tenant, tenants } = usePage().props;

    if (tenant === null) {
        return null;
    }

    if (tenants.total < 2) {
        return (
            <div
                data-test="tenant-switcher"
                className="flex min-h-11 items-center px-3 group-data-[collapsible=icon]:sr-only group-data-[collapsible=icon]:min-h-0"
            >
                <TenantName tenant={tenant} />
            </div>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <SidebarMenuButton
                    size="lg"
                    className="h-14 font-normal data-[state=open]:bg-sidebar-accent"
                    data-test="tenant-switcher"
                    tooltip={tenant.trade_name}
                >
                    <Building2 />
                    <TenantName tenant={tenant} />
                    <ChevronsUpDown className="ml-auto group-data-[collapsible=icon]:hidden" />
                </SidebarMenuButton>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                align="start"
            >
                {tenants.data.map(({ slug, trade_name }) => (
                    <DropdownMenuItem key={slug} asChild>
                        <Link
                            className="flex w-full cursor-pointer items-center justify-between"
                            href={dashboard(slug)}
                            aria-current={
                                slug === tenant.slug ? 'page' : undefined
                            }
                        >
                            <span className="truncate">{trade_name}</span>
                            {slug === tenant.slug && <Check />}
                        </Link>
                    </DropdownMenuItem>
                ))}
                {tenants.total > tenants.data.length && (
                    <DropdownMenuItem asChild>
                        <Link className="w-full cursor-pointer" href={home()}>
                            Ver las {tenants.total} empresas
                        </Link>
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
