import { useState } from 'react';
import StackedTable from '@/components/humetria/stacked-table';
import QueueRow from '@/components/leave/queue-row';
import RequestPanel from '@/components/leave/request-panel';
import {
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { LeaveQueueRequest } from '@/types';

/** The requests of a tab: each row opens its panel, where RH reads the detail and answers or corrects it. */
export default function RequestsTable({
    requests,
}: {
    requests: LeaveQueueRequest[];
}) {
    const [openId, setOpenId] = useState<string | null>(null);
    // A request that left this tab (it was answered) closes its panel.
    const open = requests.find((request) => request.id === openId);

    return (
        <>
            <StackedTable>
                <TableCaption className="sr-only">
                    Solicitudes de vacaciones y permisos
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Persona</TableHead>
                        <TableHead>Fechas</TableHead>
                        <TableHead>Días</TableHead>
                        <TableHead>Estado</TableHead>
                        <TableHead className="w-0">
                            <span className="sr-only">Detalle</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {requests.map((request) => (
                        <QueueRow
                            key={request.id}
                            request={request}
                            onOpen={() => setOpenId(request.id)}
                        />
                    ))}
                </TableBody>
            </StackedTable>
            <RequestPanel request={open} onClose={() => setOpenId(null)} />
        </>
    );
}
