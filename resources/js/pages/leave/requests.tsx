import { Head, setLayoutProps } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import RecordLeaveForm from '@/components/leave/record-leave-form';
import RequestsEmpty from '@/components/leave/requests-empty';
import RequestsToolbar from '@/components/leave/requests-toolbar';
import RequestsWorkspace from '@/components/leave/requests-workspace';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { orderLine } from '@/lib/leave/queue';
import { leaveTrail } from '@/lib/leave/trail';
import type {
    LeaveQueueFilters,
    LeaveQueueRequest,
    LeaveQueueTab,
    LeaveTypeOption,
    Paginated,
} from '@/types';

/**
 * RH's queue of vacation and leave requests: first what reached them (nobody above the person, or a manager who did not
 * answer in time), then what waits for the manager and what was answered. Each row opens its decision panel beside the
 * list, so RH reads the context and answers without leaving; the server pages it.
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

    setLayoutProps({ breadcrumbs: leaveTrail('Solicitudes') });

    return (
        <>
            <Head title="Solicitudes de vacaciones" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Solicitudes"
                    status={
                        counts.attend > 0 ? (
                            <Badge variant="warning">
                                {counts.attend} por atender
                            </Badge>
                        ) : undefined
                    }
                    description="Lo que llegó a RH, lo que espera a un jefe y lo que ya se respondió."
                >
                    {canManage && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRecording(true)}
                            className="max-sm:h-11 max-sm:w-full"
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
                    <RequestsEmpty
                        tab={tab}
                        filters={filters}
                        waiting={counts.waiting}
                    />
                ) : (
                    <>
                        <p className="text-meta text-muted-foreground">
                            {orderLine(tab)}
                        </p>
                        <RequestsWorkspace requests={requests.data} />
                    </>
                )}
                <Pagination page={requests} label="Páginas de solicitudes" />
            </div>
        </>
    );
}
