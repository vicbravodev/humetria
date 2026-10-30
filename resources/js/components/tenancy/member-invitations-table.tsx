import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cancel, resend } from '@/routes/tenant/members/invitations';
import type { MemberInvitationRow } from '@/types';

/** Invitations not accepted yet: each one is resent or cancelled from its own row. */
export default function MemberInvitationsTable({
    tenantSlug,
    invitations,
}: {
    tenantSlug: string;
    invitations: MemberInvitationRow[];
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Invitaciones sin aceptar
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Persona</TableHead>
                    <TableHead>Rol</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead>
                        <span className="sr-only">Acciones</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {invitations.map((invitation) => {
                    const ids = {
                        tenant: tenantSlug,
                        invitation: invitation.id,
                    };

                    return (
                        <TableRow key={invitation.id}>
                            <TableCell className="whitespace-normal">
                                <p className="font-medium">{invitation.name}</p>
                                <p className="text-sm text-muted-foreground">
                                    {invitation.email}
                                </p>
                            </TableCell>
                            <TableCell>{invitation.role_label}</TableCell>
                            <TableCell className="whitespace-normal">
                                <Badge
                                    variant={
                                        invitation.status === 'expired'
                                            ? 'destructive'
                                            : 'outline'
                                    }
                                >
                                    {invitation.status_label}
                                </Badge>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Vence el {invitation.expires_on}
                                </p>
                            </TableCell>
                            <TableCell className="w-px">
                                <div className="flex justify-end gap-2">
                                    <Button asChild size="sm" variant="outline">
                                        <Link
                                            href={resend(ids)}
                                            as="button"
                                            aria-label={`Reenviar la invitación de ${invitation.name}`}
                                        >
                                            Reenviar
                                        </Link>
                                    </Button>
                                    <Button asChild size="sm" variant="ghost">
                                        <Link
                                            href={cancel(ids)}
                                            as="button"
                                            aria-label={`Cancelar la invitación de ${invitation.name}`}
                                        >
                                            Cancelar
                                        </Link>
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    );
                })}
            </TableBody>
        </Table>
    );
}
