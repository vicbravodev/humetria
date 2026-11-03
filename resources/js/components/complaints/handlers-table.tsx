import { UserMinus, Users } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { describeDate } from '@/lib/calendar-date';
import { destroy } from '@/routes/tenant/complaints/handlers';
import type { HandlerRow } from '@/types';

/** The people who handle the complaints, with the scope each was designated for and the way to end it. */
export default function HandlersTable({
    handlers,
}: {
    handlers: HandlerRow[];
}) {
    const tenantSlug = useTenantSlug();

    if (handlers.length === 0) {
        return (
            <EmptyState icon={Users} title="Nadie atiende quejas todavía">
                Al activar el canal quedas designada o designado. Después puedes
                sumar a otras personas, incluso de fuera de la empresa.
            </EmptyState>
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Persona</TableHead>
                    <TableHead>Rol</TableHead>
                    <TableHead>Atiende</TableHead>
                    <TableHead>Desde</TableHead>
                    <TableHead>
                        <span className="sr-only">Acciones</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {handlers.map((handler) => (
                    <TableRow key={handler.id}>
                        <TableCell className="font-medium">
                            {handler.name}
                        </TableCell>
                        <TableCell>{handler.roleLabel}</TableCell>
                        <TableCell>{handler.scope}</TableCell>
                        <TableCell>
                            {describeDate(handler.designatedOn) ??
                                handler.designatedOn}
                        </TableCell>
                        <TableCell className="text-right">
                            <ConfirmDialog
                                title={`¿Dejar de asignar quejas a ${handler.name}?`}
                                description="Deja de recibir quejas nuevas y se avisa a las demás personas que atienden. Los casos que tenía asignados quedan sin encargada hasta que se asignen de nuevo."
                                confirmLabel="Quitar"
                                form={destroy.form({
                                    tenant: tenantSlug,
                                    handler: handler.id,
                                })}
                                trigger={
                                    <Button variant="ghost" size="sm">
                                        <UserMinus aria-hidden />
                                        Quitar
                                    </Button>
                                }
                            />
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
