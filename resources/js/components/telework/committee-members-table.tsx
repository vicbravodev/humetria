import { Form } from '@inertiajs/react';
import Initials from '@/components/humetria/initials';
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
import { destroy } from '@/routes/tenant/telework/committee/members';
import { cn } from '@/lib/utils';
import type { CommitteePerson } from '@/types';

/** Who sits on the Comisión of the center, with a way to take a seat away (pantallas: Usuarios). */
export default function CommitteeMembersTable({
    tenantSlug,
    workCenterId,
    members,
    canManage,
}: {
    tenantSlug: string;
    workCenterId: string;
    members: CommitteePerson[];
    canManage: boolean;
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Integrantes de la Comisión de seguridad e higiene del centro
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Integrante</TableHead>
                    <TableHead>Rol en la empresa</TableHead>
                    <TableHead>
                        <span className="sr-only">Acciones</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {members.map((member) => (
                    <TableRow key={member.id}>
                        <TableCell className="whitespace-normal">
                            <div className="flex items-center gap-2.5">
                                <Initials name={member.name} />
                                <div className="flex min-w-0 flex-col">
                                    <span className="font-medium">
                                        {member.name}
                                    </span>
                                    <span className="text-[13px] leading-[18px] text-muted-foreground">
                                        {member.email}
                                    </span>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell
                            className={cn(
                                'text-sm whitespace-normal',
                                member.active
                                    ? 'text-muted-foreground'
                                    : 'text-destructive',
                            )}
                        >
                            {member.role}
                        </TableCell>
                        <TableCell className="w-px text-right">
                            {canManage && (
                                <Form
                                    {...destroy.form({
                                        tenant: tenantSlug,
                                        workCenter: workCenterId,
                                        user: member.id,
                                    })}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            disabled={processing}
                                            aria-label={`Quitar a ${member.name} de la Comisión`}
                                        >
                                            Quitar
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
