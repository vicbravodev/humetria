import { ListChecks, Plus } from 'lucide-react';
import { useState } from 'react';
import EmptyState from '@/components/humetria/empty-state';
import AddRowDialog from '@/components/interventions/add-row-dialog';
import RowLine from '@/components/interventions/row-line';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ProgramRow, ProgramTools } from '@/types';

/**
 * The rows of the Programa: what it attends and the highest level that led to each (criterio C-30). At most 16 (the final
 * score, the categories and the domains of a guide), so they travel whole; HR adds the ones it wants from the guide's list.
 */
export default function ProgramRows({
    rows,
    campaignId,
    tools,
}: {
    rows: ProgramRow[];
    campaignId: string;
    tools: ProgramTools | null;
}) {
    const [adding, setAdding] = useState(false);
    const canAdd = tools !== null && tools.manualOptions.length > 0;

    return (
        <section aria-label="Filas" className="flex flex-col gap-4">
            {canAdd && (
                <div className="flex justify-end">
                    <Button variant="outline" onClick={() => setAdding(true)}>
                        <Plus aria-hidden />
                        Agregar fila
                    </Button>
                    {adding && (
                        <AddRowDialog
                            open={adding}
                            onOpenChange={setAdding}
                            campaignId={campaignId}
                            options={tools.manualOptions}
                        />
                    )}
                </div>
            )}
            {rows.length === 0 ? (
                <EmptyState
                    icon={ListChecks}
                    title="El Programa no tiene filas"
                >
                    Agrega las categorías o dominios que quieras atender.
                </EmptyState>
            ) : (
                <Table>
                    <TableCaption className="sr-only">
                        Filas del Programa de intervención
                    </TableCaption>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Qué se atiende</TableHead>
                            <TableHead>Nivel más alto</TableHead>
                            <TableHead>Acciones</TableHead>
                            <TableHead>Análisis</TableHead>
                            <TableHead className="sticky right-0 w-px bg-muted">
                                <span className="sr-only">Opciones</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row) => (
                            <RowLine
                                key={row.id}
                                row={row}
                                campaignId={campaignId}
                                tools={tools}
                            />
                        ))}
                    </TableBody>
                </Table>
            )}
        </section>
    );
}
