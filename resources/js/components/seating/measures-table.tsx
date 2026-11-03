import { ListChecks } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import MeasureRow from '@/components/seating/measure-row';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { MeasureRow as Measure, Paginated } from '@/types';

/** The measures of the action plan of a center, a page at a time. */
export default function MeasuresTable({
    workCenterId,
    measures,
    canManage,
}: {
    workCenterId: string;
    measures: Paginated<Measure>;
    canManage: boolean;
}) {
    return (
        <section aria-label="Medidas" className="flex flex-col gap-4">
            <h2 className="text-xl leading-7 font-semibold tracking-[-0.01em]">
                Medidas preventivas
            </h2>
            {measures.total === 0 ? (
                <EmptyState icon={ListChecks} title="Sin medidas todavía">
                    La Comisión propone medidas al evaluar a cada persona, y RH
                    también puede registrarlas aquí.
                </EmptyState>
            ) : (
                <>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Medida</TableHead>
                                <TableHead>Responsable</TableHead>
                                <TableHead>Fecha compromiso</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="w-px">
                                    <span className="sr-only">Acciones</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {measures.data.map((measure) => (
                                <MeasureRow
                                    key={measure.id}
                                    workCenterId={workCenterId}
                                    measure={measure}
                                    canManage={canManage}
                                />
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination
                        page={measures}
                        label="Páginas de medidas"
                        pinned={false}
                    />
                </>
            )}
        </section>
    );
}
