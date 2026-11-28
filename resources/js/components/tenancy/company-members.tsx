import Pagination from '@/components/humetria/pagination';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { CompanyMemberRow, Paginated } from '@/types';

/** The people with an account in the company: who they are, their role there and when they last came in. */
export default function CompanyMembers({
    members,
}: {
    members: Paginated<CompanyMemberRow>;
}) {
    if (members.total === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Nadie tiene cuenta en esta empresa todavía.
            </p>
        );
    }

    return (
        <div className="grid gap-3">
            <Table>
                <TableCaption className="sr-only">
                    Personas con cuenta en la empresa
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Persona</TableHead>
                        <TableHead>Rol</TableHead>
                        <TableHead className="hidden md:table-cell">
                            Último acceso
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {members.data.map((member) => (
                        <TableRow key={member.id}>
                            <TableCell className="whitespace-normal">
                                <span className="flex flex-col">
                                    {member.name}
                                    <span className="text-caption break-words text-muted-foreground">
                                        {member.email}
                                    </span>
                                </span>
                            </TableCell>
                            <TableCell className="text-sm whitespace-normal">
                                {member.role ?? 'Sin rol'}
                            </TableCell>
                            <TableCell className="hidden text-sm tabular-nums md:table-cell">
                                {member.last_access ?? 'Nunca'}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <Pagination
                page={members}
                label="Páginas de miembros"
                pinned={false}
            />
        </div>
    );
}
