import MemberInvitationRow from '@/components/tenancy/member-invitation-row';
import StackedTable from '@/components/humetria/stacked-table';
import MemberRowView from '@/components/tenancy/member-row';
import {
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

/**
 * Who has access, then the invitations still open, in one table (pantallas: Usuarios); on a phone each row is a card, the
 * name first and the role, the access and the actions after it.
 */
export default function MembersTable({
    members,
    invitations,
    roles,
}: {
    members: MemberRow[];
    invitations: Invitation[];
    roles: RoleOption[];
}) {
    return (
        <StackedTable>
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
                        member={member}
                        roles={roles}
                    />
                ))}
                {invitations.map((invitation) => (
                    <MemberInvitationRow
                        key={invitation.id}
                        invitation={invitation}
                    />
                ))}
            </TableBody>
        </StackedTable>
    );
}
