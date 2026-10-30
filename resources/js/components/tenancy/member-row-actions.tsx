import { router } from '@inertiajs/react';
import { Ellipsis, UserMinus, UserRoundCog } from 'lucide-react';
import { useState } from 'react';
import RemoveMemberDialog from '@/components/tenancy/remove-member-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { update } from '@/routes/tenant/members';
import type { MemberRow, RoleOption, TenantRole } from '@/types';

/** What the owner can do with someone's access: move them to the other role or, apart, take their access away. */
export default function MemberRowActions({
    tenantSlug,
    member,
    roles,
}: {
    tenantSlug: string;
    member: MemberRow;
    roles: RoleOption[];
}) {
    const [removing, setRemoving] = useState(false);
    const [newRole, setNewRole] = useState<TenantRole | null>(null);
    const ids = { tenant: tenantSlug, member: member.id };

    return (
        <>
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Acciones de ${member.name}`}
                        data-test="member-actions"
                        className="text-muted-foreground hover:text-foreground data-[state=open]:bg-accent data-[state=open]:text-foreground"
                    >
                        <Ellipsis className="size-5" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    className="min-w-52"
                    // The change is sent once the menu has finished closing: a page redrawn mid-animation leaves it
                    // stuck, unable to open again.
                    onCloseAutoFocus={() => {
                        if (newRole !== null) {
                            router.put(update.url(ids), { role: newRole });
                            setNewRole(null);
                        }
                    }}
                >
                    {roles
                        .filter((role) => role.value !== member.role)
                        .map((role) => (
                            <DropdownMenuItem
                                key={role.value}
                                onSelect={() => setNewRole(role.value)}
                            >
                                <UserRoundCog />
                                Cambiar a {role.label}
                            </DropdownMenuItem>
                        ))}
                    <DropdownMenuSeparator />
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
                tenantSlug={tenantSlug}
                member={member}
                open={removing}
                onOpenChange={setRemoving}
            />
        </>
    );
}
