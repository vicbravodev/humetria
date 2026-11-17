import WorkCenterRowActions from '@/components/tenancy/work-center-row-actions';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { MexicanStateOption, WorkCenterRow } from '@/types';

/**
 * The archived work centers of one page as a table (decisiones: lo terminado queda como archivo consultable): what each
 * was and where, its people and the day it was archived; the only thing left to do is restore it.
 */
export default function WorkCentersArchivedTable({
    workCenters,
    states,
}: {
    workCenters: WorkCenterRow[];
    /** Null when the person may not change work centers: no actions column. */
    states: MexicanStateOption[] | null;
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Centros de trabajo archivados
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Centro de trabajo</TableHead>
                    <TableHead className="text-right">Personas</TableHead>
                    <TableHead>Archivado el</TableHead>
                    {states !== null && (
                        <TableHead>
                            <span className="sr-only">Acciones</span>
                        </TableHead>
                    )}
                </TableRow>
            </TableHeader>
            <TableBody>
                {workCenters.map((workCenter) => (
                    <TableRow key={workCenter.id}>
                        <TableCell className="min-w-64 whitespace-normal">
                            <div className="flex flex-col">
                                <span className="font-medium">
                                    {workCenter.name}
                                </span>
                                <span className="text-caption text-muted-foreground">
                                    {workCenter.address}
                                </span>
                            </div>
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                            {workCenter.employees}
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                            {workCenter.archived_on}
                        </TableCell>
                        {states !== null && (
                            <TableCell className="w-px text-right">
                                <WorkCenterRowActions
                                    workCenter={workCenter}
                                    states={states}
                                />
                            </TableCell>
                        )}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
