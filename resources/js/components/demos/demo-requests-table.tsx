import DemoRequestRow from '@/components/demos/demo-request-row';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { DemoRequestRow as DemoRequest } from '@/types';

export default function DemoRequestsTable({
    requests,
}: {
    requests: DemoRequest[];
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Solicitudes de demo, de la más reciente a la más antigua
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Solicitante</TableHead>
                    <TableHead>Contacto</TableHead>
                    <TableHead>Perfil</TableHead>
                    <TableHead>Mensaje</TableHead>
                    <TableHead>Recibida</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {requests.map((request) => (
                    <DemoRequestRow key={request.id} request={request} />
                ))}
            </TableBody>
        </Table>
    );
}
