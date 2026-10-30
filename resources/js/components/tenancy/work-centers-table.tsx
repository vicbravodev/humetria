import WorkCenterRowView from '@/components/tenancy/work-center-row';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    MexicanStateOption,
    WorkCenterRow,
    WorkCentersView,
} from '@/types';

export default function WorkCentersTable({
    tenantSlug,
    view,
    workCenters,
    states,
}: {
    tenantSlug: string;
    view: WorkCentersView;
    workCenters: WorkCenterRow[];
    /** The states the edit form offers; null when the person may not change work centers, so no actions show. */
    states: MexicanStateOption[] | null;
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                {view === 'active'
                    ? 'Centros de trabajo, por nombre'
                    : 'Centros de trabajo archivados, por nombre'}
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Centro de trabajo</TableHead>
                    <TableHead>Domicilio</TableHead>
                    <TableHead>Personal</TableHead>
                    {view === 'archived' && <TableHead>Archivado</TableHead>}
                    {states !== null && (
                        <TableHead>
                            <span className="sr-only">Acciones</span>
                        </TableHead>
                    )}
                </TableRow>
            </TableHeader>
            <TableBody>
                {workCenters.map((workCenter) => (
                    <WorkCenterRowView
                        key={workCenter.id}
                        tenantSlug={tenantSlug}
                        workCenter={workCenter}
                        states={states}
                    />
                ))}
            </TableBody>
        </Table>
    );
}
