import Pagination from '@/components/humetria/pagination';
import RequestRow from '@/components/leave/request-row';
import type { Paginated, PortalLeaveRequest } from '@/types';

/** What the person has asked for, the newest dates first, a few at a time. */
export default function PortalRequests({
    requests,
}: {
    requests: Paginated<PortalLeaveRequest>;
}) {
    return (
        <section aria-labelledby="leave-requests" className="grid gap-3">
            <h2 id="leave-requests" className="text-xl font-semibold">
                Sus solicitudes
            </h2>
            {requests.data.length === 0 ? (
                <p className="text-base text-muted-foreground">
                    Aún no ha pedido días. Lo que pida aparecerá aquí con su
                    respuesta.
                </p>
            ) : (
                <ul className="divide-y card">
                    {requests.data.map((request) => (
                        <RequestRow key={request.id} request={request} />
                    ))}
                </ul>
            )}
            <Pagination
                page={requests}
                label="Sus solicitudes"
                previous="Ver más recientes"
                next="Ver más antiguas"
                pinned={false}
            />
        </section>
    );
}
