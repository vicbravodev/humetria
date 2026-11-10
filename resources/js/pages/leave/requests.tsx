import { Head, setLayoutProps } from '@inertiajs/react';
import { Inbox, Plus } from 'lucide-react';
import { useState } from 'react';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import RecordLeaveForm from '@/components/leave/record-leave-form';
import RequestsTable from '@/components/leave/requests-table';
import RequestsToolbar from '@/components/leave/requests-toolbar';
import { Button } from '@/components/ui/button';
import type {
    LeaveQueueFilters,
    LeaveQueueRequest,
    LeaveQueueTab,
    LeaveTypeOption,
    Paginated,
} from '@/types';

const EMPTY: Record<LeaveQueueTab, string> = {
    attend: 'Nada por atender. Las solicitudes que su jefe no responda a tiempo llegarán aquí.',
    waiting: 'Ninguna solicitud espera a un jefe.',
    approved: 'Aún no hay solicitudes aprobadas.',
    rejected: 'Aún no hay solicitudes rechazadas.',
    cancelled: 'Aún no hay solicitudes canceladas.',
    taken: 'Aún no hay solicitudes disfrutadas.',
};

/**
 * RH's queue of vacation and leave requests: first what reached them (nobody above the person, or a manager who did not
 * answer in time), then what waits for the manager and what was answered, paged by the server.
 */
export default function LeaveRequests({
    tab,
    counts,
    filters,
    canManage,
    types,
    requests,
}: {
    tab: LeaveQueueTab;
    counts: Record<LeaveQueueTab, number>;
    filters: Omit<LeaveQueueFilters, 'tab'>;
    canManage: boolean;
    types: LeaveTypeOption[];
    requests: Paginated<LeaveQueueRequest>;
}) {
    const [recording, setRecording] = useState(false);

    setLayoutProps({
        breadcrumbs: [{ title: 'Vacaciones' }, { title: 'Solicitudes' }],
    });

    return (
        <>
            <Head title="Solicitudes de vacaciones" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Vacaciones"
                    title="Solicitudes"
                    description="Lo que llegó a RH, lo que espera a un jefe y lo que ya se respondió."
                >
                    {canManage && (
                        <Button
                            type="button"
                            onClick={() => setRecording(true)}
                        >
                            <Plus aria-hidden />
                            Registrar a nombre de alguien
                        </Button>
                    )}
                </PageHeader>
                {canManage && (
                    <RecordLeaveForm
                        open={recording}
                        onOpenChange={setRecording}
                        types={types}
                    />
                )}
                <RequestsToolbar
                    filters={{ ...filters, tab }}
                    counts={counts}
                />
                {requests.data.length === 0 ? (
                    <EmptyState icon={Inbox} title="Sin solicitudes aquí">
                        {EMPTY[tab]}
                    </EmptyState>
                ) : (
                    <RequestsTable requests={requests.data} />
                )}
                <Pagination page={requests} label="Páginas de solicitudes" />
            </div>
        </>
    );
}
