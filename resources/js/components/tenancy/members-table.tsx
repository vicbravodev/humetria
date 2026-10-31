import MemberInvitationRow from '@/components/tenancy/member-invitation-row';
import MemberRowView from '@/components/tenancy/member-row';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    MemberInvitationRow as Invitation,
    MemberRow,
    RoleOption,
} from '@/types';

/** Who has access, then the invitations still open, in one table (pantallas: Usuarios). */
export default function MembersTable({
    tenantSlug,
    members,
    invitations,
    roles,
}: {
    tenantSlug: string;
    members: MemberRow[];
    invitations: Invitation[];
    roles: RoleOption[];
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Personas con acceso a la empresa e invitaciones sin aceptar
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Persona</TableHead>
                    <TableHead>Rol</TableHead>
                    <TableHead>Acceso</TableHead>
                    <TableHead>
                        <span className="sr-only">Acciones</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {members.map((member) => (
                    <MemberRowView
                        key={member.id}
                        tenantSlug={tenantSlug}
                        member={member}
                        roles={roles}
                    />
                ))}
                {invitations.map((invitation) => (
                    <MemberInvitationRow
                        key={invitation.id}
                        tenantSlug={tenantSlug}
                        invitation={invitation}
                    />
                ))}
            </TableBody>
        </Table>
    );
}
