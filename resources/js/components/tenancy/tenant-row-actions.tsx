import { Link, router } from '@inertiajs/react';
import {
    Bot,
    Ellipsis,
    ImageIcon,
    LogIn,
    Pencil,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import ActivateTenantDialog from '@/components/tenancy/activate-tenant-dialog';
import EditTenantDialog from '@/components/tenancy/edit-tenant-dialog';
import HumiSettingsDialog from '@/components/tenancy/humi-settings-dialog';
import InviteOwnerDialog from '@/components/tenancy/invite-owner-dialog';
import ReactivateTenantDialog from '@/components/tenancy/reactivate-tenant-dialog';
import RestrictTenantDialog from '@/components/tenancy/restrict-tenant-dialog';
import TenantLogoDialog from '@/components/tenancy/tenant-logo-dialog';
import TenantRestrictionItems from '@/components/tenancy/tenant-restriction-items';
import TenantTermItem from '@/components/tenancy/tenant-term-item';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { type Restriction } from '@/lib/tenant-restriction';
import { ownerAction } from '@/lib/tenant-status';
import { impersonate } from '@/routes/companies';
import type { TenantRow } from '@/types';

type Action =
    | 'activate'
    | 'reactivate'
    | 'invite'
    | 'edit'
    | 'logo'
    | 'humi'
    | Restriction;

const RESTRICTIONS: Restriction[] = ['read_only', 'suspended', 'archived'];

/**
 * Everything the superadmin can do with a company, behind one "…" button so the row stays narrow.
 * Each item opens its dialog; the restrictions (read only, suspend, archive) sit apart.
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
                    <TenantTermItem tenant={tenant} onChoose={setOpen} />
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
                    <DropdownMenuItem onSelect={() => setOpen('logo')}>
                        <ImageIcon />
                        Logo de los correos
                    </DropdownMenuItem>
                    <DropdownMenuItem onSelect={() => setOpen('humi')}>
                        <Bot />
                        Asistente Humi
                    </DropdownMenuItem>
                    <TenantRestrictionItems
                        tenant={tenant}
                        onChoose={setOpen}
                    />
                </DropdownMenuContent>
            </DropdownMenu>
            <ActivateTenantDialog
                {...dialog('activate')}
                minDate={activationMinDate}
            />
            <InviteOwnerDialog {...dialog('invite')} />
            <EditTenantDialog {...dialog('edit')} />
            <TenantLogoDialog {...dialog('logo')} />
            <HumiSettingsDialog
                tenantId={tenant.id}
                tradeName={tenant.trade_name}
                open={open === 'humi'}
                onOpenChange={(isOpen) => setOpen(isOpen ? 'humi' : null)}
            />
            <ReactivateTenantDialog {...dialog('reactivate')} />
            {RESTRICTIONS.map((restriction) => (
                <RestrictTenantDialog
                    key={restriction}
                    restriction={restriction}
                    {...dialog(restriction)}
                />
            ))}
        </>
    );
}
