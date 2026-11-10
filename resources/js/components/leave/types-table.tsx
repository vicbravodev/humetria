import { Plus } from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/humetria/pagination';
import StackedTable from '@/components/humetria/stacked-table';
import TypeDialog from '@/components/leave/type-dialog';
import TypeRow from '@/components/leave/type-row';
import { Button } from '@/components/ui/button';
import {
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { LeaveTypeRow, Paginated } from '@/types';

/** The leave types of the company: the five of the law, which stay as they are, and its own, which RH adds and archives. */
export default function TypesTable({
    types,
    canManage,
}: {
    types: Paginated<LeaveTypeRow>;
    canManage: boolean;
}) {
    const [adding, setAdding] = useState(false);

    return (
        <section aria-label="Tipos de permiso" className="grid gap-4">
            {canManage && (
                <div className="flex justify-end">
                    <Button onClick={() => setAdding(true)}>
                        <Plus />
                        Nuevo tipo de permiso
                    </Button>
                    <TypeDialog
                        open={adding}
                        onOpenChange={setAdding}
                        type={null}
                    />
                </div>
            )}
            <StackedTable>
                <TableCaption className="sr-only">
                    Tipos de permiso
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Permiso</TableHead>
                        <TableHead>Goce</TableHead>
                        <TableHead>Origen</TableHead>
                        <TableHead className="w-0">
                            <span className="sr-only">Acciones</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {types.data.map((type) => (
                        <TypeRow
                            key={type.id}
                            type={type}
                            canManage={canManage}
                        />
                    ))}
                </TableBody>
            </StackedTable>
            <Pagination
                page={types}
                label="Páginas de tipos de permiso"
                pinned={false}
            />
        </section>
    );
}
