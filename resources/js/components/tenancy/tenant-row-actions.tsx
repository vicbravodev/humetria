import { Link, router } from '@inertiajs/react';
import {
    Ban,
    CalendarCheck,
    Ellipsis,
    LogIn,
    Pencil,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import ActivateTenantDialog from '@/components/tenancy/activate-tenant-dialog';
import DeactivateTenantDialog from '@/components/tenancy/deactivate-tenant-dialog';
import EditTenantDialog from '@/components/tenancy/edit-tenant-dialog';
import InviteOwnerDialog from '@/components/tenancy/invite-owner-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { activationCopy, ownerAction } from '@/lib/tenant-status';
import { impersonate } from '@/routes/companies';
import type { TenantRow } from '@/types';

type Action = 'activate' | 'invite' | 'edit' | 'deactivate';

/**
 * Everything the superadmin can do with a company, behind one "…" button so the row stays narrow.
 * Each item opens its dialog; deactivating sits apart, in the destructive color.
 */
export default function TenantRowActions({
    tenant,
    activationMinDate,
}: {
    tenant: TenantRow;
    activationMinDate: string;
}) {
    const [open, setOpen] = useState<Action | null>(null);
    const dialog = (action: Action) => ({
        tenant,
        open: open === action,
        onOpenChange: (isOpen: boolean) => setOpen(isOpen ? action : null),
    });

    return (
        <>
            {/* Not modal: the dialog an item opens takes over focus as the menu closes. */}
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Acciones de ${tenant.trade_name}`}
                        data-test="tenant-actions"
                        className="text-muted-foreground hover:text-foreground data-[state=open]:bg-accent data-[state=open]:text-foreground"
                    >
                        <Ellipsis className="size-5" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="min-w-52">
                    <DropdownMenuItem onSelect={() => setOpen('activate')}>
                        <CalendarCheck />
                        {activationCopy(tenant.status).action}
                    </DropdownMenuItem>
                    <DropdownMenuItem onSelect={() => setOpen('invite')}>
                        <UserPlus />
                        {ownerAction(tenant.owner)}
                    </DropdownMenuItem>
                    {tenant.owner !== null && (
                        <DropdownMenuItem asChild>
                            {/* Another person's session: nothing prefetched as the superadmin carries over. */}
                            <Link
                                href={impersonate(tenant.id)}
                                as="button"
                                onClick={() => router.flushAll()}
                                className="w-full"
                            >
                                <LogIn />
                                Entrar como owner
                            </Link>
                        </DropdownMenuItem>
                    )}
                    <DropdownMenuItem onSelect={() => setOpen('edit')}>
                        <Pencil />
                        Editar
                    </DropdownMenuItem>
                    {!tenant.blocked && (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => setOpen('deactivate')}
                            >
                                <Ban />
                                Desactivar
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
            <ActivateTenantDialog
                {...dialog('activate')}
                minDate={activationMinDate}
            />
            <InviteOwnerDialog {...dialog('invite')} />
            <EditTenantDialog {...dialog('edit')} />
            <DeactivateTenantDialog {...dialog('deactivate')} />
        </>
    );
}
