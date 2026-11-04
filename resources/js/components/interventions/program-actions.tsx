import { ListChecks } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import ActionFilters from '@/components/interventions/action-filters';
import ActionLine from '@/components/interventions/action-line';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    ActionFilters as Filters,
    Paginated,
    ProgramAction,
    ProgramRow,
    ProgramTools,
} from '@/types';

/**
 * The actions of the Programa (num. 8.4 b, c and f), a page at a time and filtered on the server by row and state: the
 * soonest due first, so what is overdue comes up. A cancelled one stays in the list: nothing is deleted.
 */
export default function ProgramActions({
    url,
    actions,
    rows,
    filters,
    campaignId,
    tools,
}: {
    url: string;
    actions: Paginated<ProgramAction>;
    rows: ProgramRow[];
    filters: Filters;
    campaignId: string;
    tools: ProgramTools | null;
}) {
    return (
        <section aria-label="Acciones" className="flex flex-col gap-4">
            <ActionFilters url={url} rows={rows} filters={filters} />
            {actions.total === 0 ? (
                <EmptyState icon={ListChecks} title="Sin acciones todavía">
                    {tools === null
                        ? 'Cuando RH registre acciones aparecerán aquí.'
                        : 'Agrega acciones desde el menú de cada fila del Programa.'}
                </EmptyState>
            ) : (
                <>
                    <Table>
                        <TableCaption className="sr-only">
                            Acciones del Programa de intervención
                        </TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Acción</TableHead>
                                <TableHead>Fila</TableHead>
                                <TableHead>Para quién</TableHead>
                                <TableHead>Fecha límite</TableHead>
                                <TableHead>Responsable</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="w-px">
                                    <span className="sr-only">Opciones</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {actions.data.map((action) => (
                                <ActionLine
                                    key={action.id}
                                    action={action}
                                    campaignId={campaignId}
                                    rows={rows}
                                    tools={tools}
                                />
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination
                        page={actions}
                        label="Páginas de acciones"
                        pinned={false}
                    />
                </>
            )}
        </section>
    );
}
