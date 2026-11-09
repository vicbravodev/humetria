import { ClipboardCheck } from 'lucide-react';
import CorrectionListItem from '@/components/attendance/correction-list-item';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import type { CorrectionRow, CorrectionStatus, Paginated } from '@/types';

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

/** The requests of one state, a page at a time, the one in view marked; or what will appear here. */
export default function CorrectionList({
    requests,
    selectedId,
    status,
    q,
}: {
    requests: Paginated<CorrectionRow>;
    selectedId: string | null;
    status: CorrectionStatus;
    q: string;
}) {
    if (requests.total === 0) {
        return (
            <EmptyState
                icon={ClipboardCheck}
                title={q !== '' ? 'Nadie con ese nombre' : EMPTY[status].title}
            >
                {q !== ''
                    ? 'Revisa cómo lo escribiste o busca con otra parte del nombre.'
                    : EMPTY[status].text}
            </EmptyState>
        );
    }

    return (
        <div className="flex flex-col gap-3">
            <ul
                aria-label="Solicitudes"
                className="divide-y overflow-hidden card"
            >
                {requests.data.map((correction) => (
                    <CorrectionListItem
                        key={correction.id}
                        correction={correction}
                        selected={correction.id === selectedId}
                        status={status}
                        q={q}
                        page={requests.current_page}
                    />
                ))}
            </ul>
            <Pagination
                page={requests}
                label="Páginas de solicitudes"
                pinned={false}
            />
        </div>
    );
}
