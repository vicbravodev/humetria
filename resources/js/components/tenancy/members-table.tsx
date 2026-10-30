import { Badge } from '@/components/ui/badge';
import MemberRowActions from '@/components/tenancy/member-row-actions';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { MemberRow, RoleOption } from '@/types';

export default function MembersTable({
    tenantSlug,
    members,
    roles,
}: {
    tenantSlug: string;
    members: MemberRow[];
    roles: RoleOption[];
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Personas con acceso a la empresa
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Persona</TableHead>
                    <TableHead>Rol</TableHead>
                    <TableHead>Desde</TableHead>
                    <TableHead>
                        <span className="sr-only">Acciones</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {members.map((member) => (
                    <TableRow key={member.id}>
                        <TableCell className="whitespace-normal">
                            <p className="font-medium">
                                {member.name}
                                {member.is_you && (
                                    <span className="text-muted-foreground">
                                        {' '}
                                        (tú)
                                    </span>
                                )}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {member.email}
                            </p>
                        </TableCell>
                        <TableCell>
                            <Badge
                                variant={
                                    member.role === 'owner'
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {member.role_label}
                            </Badge>
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                            {member.since}
                        </TableCell>
                        <TableCell className="w-px text-right">
                            {/* The owner is changed from the superadmin's console, never here. */}
                            {member.role !== 'owner' && (
                                <MemberRowActions
                                    tenantSlug={tenantSlug}
                                    member={member}
                                    roles={roles}
                                />
                            )}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
