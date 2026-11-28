import { Inbox, SearchX } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { demoEmptyText } from '@/lib/demo-requests';
import type { DemoFilters } from '@/types';

export default function DemoRequestsEmpty({
    filters,
}: {
    filters: DemoFilters;
}) {
    const narrowed = [
        filters.q.trim(),
        filters.origin,
        filters.owner,
        filters.size,
    ].some((value) => value !== undefined && value !== '');

    if (narrowed) {
        return (
            <EmptyState icon={SearchX} title="Ninguna solicitud coincide">
                Prueba con otro nombre, correo, empresa o WhatsApp, o quita un
                filtro.
            </EmptyState>
        );
    }

    return (
        <EmptyState
            icon={Inbox}
            title={
                filters.status === 'all'
                    ? 'Aún no hay solicitudes'
                    : 'No hay solicitudes en este estado'
            }
        >
            {demoEmptyText(filters.status)}
        </EmptyState>
    );
}
