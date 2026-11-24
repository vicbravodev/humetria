import { CloudOff } from 'lucide-react';
import OfflineRejectedItem from '@/components/attendance/offline-rejected-item';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import type { Paginated, RejectedOfflineRow } from '@/types';

/** The deliveries of the last 35 days that were not accepted, a page at a time: what happened to each, to say it to the person. */
export default function OfflineRejectedList({
    deliveries,
    q,
}: {
    deliveries: Paginated<RejectedOfflineRow>;
    q: string;
}) {
    if (deliveries.total === 0) {
        return (
            <EmptyState
                icon={CloudOff}
                title={q !== '' ? 'Nadie con ese nombre' : 'Nada sin aceptar'}
            >
                {q !== ''
                    ? 'Revisa cómo lo escribiste o busca con otra parte del nombre.'
                    : 'Ninguna marca se rechazó en los últimos 35 días.'}
            </EmptyState>
        );
    }

    return (
        <div className="flex flex-col gap-3">
            <p className="text-sm text-muted-foreground">
                Estas marcas no entraron al registro. Si hace falta, la persona
                pide una corrección desde «Mi semana».
            </p>
            <ul
                aria-label="Marcas sin conexión no aceptadas"
                className="divide-y overflow-hidden card"
            >
                {deliveries.data.map((row) => (
                    <OfflineRejectedItem key={row.id} row={row} />
                ))}
            </ul>
            <Pagination
                page={deliveries}
                label="Páginas de marcas no aceptadas"
                pinned={false}
            />
        </div>
    );
}
