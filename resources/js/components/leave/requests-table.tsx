import StackedTable from '@/components/humetria/stacked-table';
import QueueRow from '@/components/leave/queue-row';
import {
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { LeaveQueueRequest } from '@/types';

/** The requests of a tab, a row each; the row that is selected shows its decision panel. */
export default function RequestsTable({
    requests,
    selectedId,
    onSelect,
}: {
    requests: LeaveQueueRequest[];
    selectedId: string | undefined;
    onSelect: (id: string) => void;
}) {
    return (
        <div className="overflow-hidden card p-0">
            <StackedTable>
                <TableCaption className="sr-only">
                    Solicitudes de vacaciones y permisos
                </TableCaption>
                <TableHeader>
                    <TableRow className="bg-muted hover:bg-muted">
                        <TableHead>Persona</TableHead>
                        <TableHead>Fechas</TableHead>
                        <TableHead className="text-right">Días</TableHead>
                        <TableHead>Espera desde</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {requests.map((request) => (
                        <QueueRow
                            key={request.id}
                            request={request}
                            selected={request.id === selectedId}
                            onSelect={() => onSelect(request.id)}
                        />
                    ))}
                </TableBody>
            </StackedTable>
        </div>
    );
}
