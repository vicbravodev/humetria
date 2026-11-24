import { router } from '@inertiajs/react';
import { Ellipsis, KeyRound, UserMinus } from 'lucide-react';
import { useState } from 'react';
import RemoveMemberDialog from '@/components/tenancy/remove-member-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { edit } from '@/routes/tenant/members/permissions';
import type { MemberRow } from '@/types';

/** Behind the "…" of someone's row: their permissions besides their role, and taking their access away, after confirming. Their role changes in its own cell. */
export default function MemberRowActions({ member }: { member: MemberRow }) {
    const [removing, setRemoving] = useState(false);
    const tenantSlug = useTenantSlug();

    return (
        <>
            {/* Not modal: the dialog the item opens takes over focus as the menu closes. */}
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Acciones de ${member.name}`}
                        data-test="member-actions"
                        className="-my-2 text-muted-foreground hover:text-foreground data-[state=open]:bg-accent data-[state=open]:text-foreground"
                    >
                        <Ellipsis className="size-5" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="min-w-44">
                    <DropdownMenuItem
                        onSelect={() =>
                            router.visit(
                                edit({ tenant: tenantSlug, member: member.id }),
                            )
                        }
                    >
                        <KeyRound />
                        Permisos adicionales
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={() => setRemoving(true)}
                    >
                        <UserMinus />
                        Quitar acceso
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <RemoveMemberDialog
                member={member}
                open={removing}
                onOpenChange={setRemoving}
            />
        </>
    );
}
