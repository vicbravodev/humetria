import { ListChecks } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import MeasureColumn from '@/components/seating/measure-column';
import { measureColumns } from '@/lib/seating/plan';
import type { MeasureRow, Paginated } from '@/types';

/** The measures of the plan of a center by where they stand (board LS-Plan), a page at a time. */
export default function MeasuresBoard({
    workCenterId,
    measures,
    canManage,
}: {
    workCenterId: string;
    measures: Paginated<MeasureRow>;
    canManage: boolean;
}) {
    if (measures.total === 0) {
        return (
            <EmptyState icon={ListChecks} title="Sin medidas todavía">
                La Comisión propone medidas al evaluar a cada persona, y RH
                también puede registrarlas aquí.
            </EmptyState>
        );
    }

    const { columns, cancelled } = measureColumns(measures.data);

    return (
        <section aria-label="Medidas" className="flex flex-col gap-4">
            <div className="grid gap-4 md:grid-cols-3">
                {columns.map((column) => (
                    <MeasureColumn
                        key={column.status}
                        workCenterId={workCenterId}
                        column={column}
                        canManage={canManage}
                    />
                ))}
            </div>
            {cancelled > 0 && (
                <p className="text-sm text-muted-foreground">
                    {cancelled === 1
                        ? '1 medida cancelada queda en el historial.'
                        : `${cancelled} medidas canceladas quedan en el historial.`}
                </p>
            )}
            <Pagination
                page={measures}
                label="Páginas de medidas"
                pinned={false}
            />
        </section>
    );
}
