import { useState } from 'react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import TypeDialog from '@/components/leave/type-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { archive } from '@/routes/tenant/vacations/types';
import type { LeaveTypeRow } from '@/types';

/** A leave type: its name, whether it is paid and, for the company's own, change and archive. */
export default function TypeRow({
    type,
    canManage,
}: {
    type: LeaveTypeRow;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const [editing, setEditing] = useState(false);
    const own = canManage && !type.statutory && !type.archived;

    return (
        <TableRow>
            <TableCell className="font-medium whitespace-normal max-md:w-full">
                {type.name}
            </TableCell>
            <TableCell>
                <Badge variant="outline">
                    {type.paid ? 'Con goce' : 'Sin goce'}
                </Badge>
            </TableCell>
            <TableCell>
                {type.archived ? (
                    <Badge variant="secondary">Archivado</Badge>
                ) : (
                    <Badge variant={type.statutory ? 'default' : 'success'}>
                        {type.statutory ? 'De la ley' : 'De la empresa'}
                    </Badge>
                )}
            </TableCell>
            <TableCell className="text-right">
                {own && (
                    <div className="flex justify-end gap-1">
                        <Button
                            variant="ghost"
                            onClick={() => setEditing(true)}
                        >
                            Cambiar
                        </Button>
                        <ConfirmDialog
                            trigger={<Button variant="ghost">Archivar</Button>}
                            title={`¿Archivar ${type.name}?`}
                            description="Dejará de ofrecerse. Las solicitudes ya hechas lo conservan."
                            confirmLabel="Archivar"
                            form={archive.form({
                                tenant: tenantSlug,
                                type: type.id,
                            })}
                            preserveScroll
                        />
                        <TypeDialog
                            open={editing}
                            onOpenChange={setEditing}
                            type={type}
                        />
                    </div>
                )}
            </TableCell>
        </TableRow>
    );
}
