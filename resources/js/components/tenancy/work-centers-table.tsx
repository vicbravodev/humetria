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
    states: MexicanStateOption[];
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
                    {view === 'archived' && <TableHead>Archivado</TableHead>}
                    <TableHead>
                        <span className="sr-only">Acciones</span>
                    </TableHead>
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
