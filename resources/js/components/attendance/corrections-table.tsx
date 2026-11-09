import { ClipboardCheck } from 'lucide-react';
import CorrectionRow from '@/components/attendance/correction-row';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    CorrectionRow as Row,
    CorrectionStatus,
    Paginated,
} from '@/types';

const EMPTY: Record<CorrectionStatus, { title: string; text: string }> = {
    pending: {
        title: 'Nada por resolver',
        text: 'Cuando una persona pida corregir una marca desde «Mi semana» o desde Marcar, aparecerá aquí con su motivo.',
    },
    approved: {
        title: 'Sin solicitudes aprobadas',
        text: 'Las que apruebes quedan aquí, con la marca corregida en el registro de la persona.',
    },
    rejected: {
        title: 'Sin solicitudes rechazadas',
        text: 'Las que rechaces quedan aquí con tu nota; la persona la ve en «Mi semana».',
    },
};

/** The requests of one state, a page at a time, or what will appear here. */
export default function CorrectionsTable({
    requests,
    status,
    searching,
    canResolve,
}: {
    requests: Paginated<Row>;
    status: CorrectionStatus;
    searching: boolean;
    canResolve: boolean;
}) {
    if (requests.total === 0) {
        return (
            <EmptyState
                icon={ClipboardCheck}
                title={searching ? 'Nadie con ese nombre' : EMPTY[status].title}
            >
                {searching
                    ? 'Revisa cómo lo escribiste o busca con otra parte del nombre.'
                    : EMPTY[status].text}
            </EmptyState>
        );
    }

    return (
        <>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Persona</TableHead>
                        <TableHead>Pide</TableHead>
                        <TableHead>Motivo</TableHead>
                        <TableHead>Espera</TableHead>
                        <TableHead>
                            {status === 'pending' ? 'Resolver' : 'Resolución'}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {requests.data.map((correction) => (
                        <CorrectionRow
                            key={correction.id}
                            correction={correction}
                            canResolve={canResolve}
                        />
                    ))}
                </TableBody>
            </Table>
            <Pagination
                page={requests}
                label="Páginas de solicitudes"
                pinned={false}
            />
        </>
    );
}
