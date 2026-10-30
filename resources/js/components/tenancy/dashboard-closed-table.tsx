import ConsolePagination from '@/components/console-pagination';
import DashboardClosedRow from '@/components/tenancy/dashboard-closed-row';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ClosedEvaluation, Paginated, RiskLevelInfo } from '@/types';

/** "Evaluaciones cerradas" (pantalla Panel): participation and final score of each, with its results and its report. */
export default function DashboardClosedTable({
    tenantSlug,
    closed,
    levels,
}: {
    tenantSlug: string;
    closed: Paginated<ClosedEvaluation>;
    levels: RiskLevelInfo[];
}) {
    return (
        <section aria-labelledby="closed" className="flex flex-col gap-3.5">
            <h2 id="closed" className="text-lg leading-[26px] font-semibold">
                Evaluaciones cerradas
            </h2>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Centro de trabajo</TableHead>
                        <TableHead>Cerró</TableHead>
                        <TableHead className="text-right">
                            Participación
                        </TableHead>
                        <TableHead className="w-[34%]">
                            Calificación final
                        </TableHead>
                        <TableHead>
                            <span className="sr-only">Acciones</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {closed.data.map((evaluation) => (
                        <DashboardClosedRow
                            key={evaluation.id}
                            tenantSlug={tenantSlug}
                            evaluation={evaluation}
                            levels={levels}
                        />
                    ))}
                </TableBody>
            </Table>
            <ConsolePagination
                page={closed}
                label="Páginas de evaluaciones cerradas"
                pinned={false}
            />
        </section>
    );
}
