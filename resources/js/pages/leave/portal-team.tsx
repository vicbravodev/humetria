import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Inbox } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import DecisionCard from '@/components/leave/decision-card';
import PortalShell from '@/components/leave/portal-shell';
import { show } from '@/routes/portal/vacations';
import type { Paginated, TeamLeaveRequest } from '@/types';

/**
 * The requests of the person's team that wait for their answer, in usted: each one with where its days would come from,
 * to approve or to reject with a reason. Only what is theirs to answer.
 */
export default function LeavePortalTeam({
    token,
    company,
    requests,
}: {
    token: string;
    company: string;
    name: string;
    requests: Paginated<TeamLeaveRequest>;
}) {
    return (
        <PortalShell company={company}>
            <Head title="Solicitudes de mi equipo" />
            <div className="flex w-full max-w-2xl flex-col gap-6">
                <Link
                    href={show.url({ token })}
                    className="touch-hit flex w-fit items-center gap-1.5 text-base font-medium text-primary"
                >
                    <ArrowLeft aria-hidden className="size-4" />
                    Volver a mis vacaciones
                </Link>
                <div className="flex flex-col gap-2">
                    <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        Solicitudes de mi equipo
                    </h1>
                    <p className="text-muted-foreground">
                        Las solicitudes que esperan su respuesta. Quien pidió
                        recibe un aviso con lo que usted decida.
                    </p>
                </div>
                {requests.total === 0 ? (
                    <EmptyState
                        icon={Inbox}
                        title="No tiene solicitudes por responder."
                    >
                        Cuando alguien de su equipo pida días, lo verá aquí y le
                        avisaremos por correo.
                    </EmptyState>
                ) : (
                    <ul className="grid gap-3">
                        {requests.data.map((request) => (
                            <DecisionCard
                                key={request.id}
                                token={token}
                                request={request}
                            />
                        ))}
                    </ul>
                )}
                <Pagination
                    page={requests}
                    label="Solicitudes de mi equipo"
                    pinned={false}
                />
            </div>
        </PortalShell>
    );
}
