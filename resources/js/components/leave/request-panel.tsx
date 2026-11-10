import RequestActions from '@/components/leave/request-actions';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { dateSpan, workingDaysOf } from '@/lib/leave/format';
import { daysLine } from '@/lib/leave/queue';
import type { LeaveQueueRequest } from '@/types';

/** What RH reads of a request before answering it: the dates, the days and where they come from, and its note. */
export default function RequestPanel({
    request,
    onClose,
}: {
    request: LeaveQueueRequest | undefined;
    onClose: () => void;
}) {
    return (
        <Sheet open={request !== undefined} onOpenChange={onClose}>
            <SheetContent className="w-full gap-0 bg-popover sm:inset-y-2 sm:right-2 sm:h-auto sm:max-w-[480px] sm:rounded-xl sm:border-0 sm:shadow-float">
                {request !== undefined && (
                    <>
                        <SheetHeader className="gap-0.5 border-b px-6 pt-5 pb-4">
                            <SheetTitle className="text-lg leading-[26px]">
                                {request.name}
                            </SheetTitle>
                            <SheetDescription>
                                {request.type ?? 'Vacaciones'} ·{' '}
                                {request.statusLabel}
                            </SheetDescription>
                        </SheetHeader>
                        <dl className="grid flex-1 content-start gap-x-4 gap-y-3 overflow-y-auto px-6 py-5 sm:grid-cols-[120px_minmax(0,1fr)]">
                            <dt className="text-sm text-muted-foreground">
                                Fechas
                            </dt>
                            <dd>
                                {dateSpan(request.startsOn, request.endsOn)} ·{' '}
                                {workingDaysOf(request.workingDays)}
                            </dd>
                            <dt className="text-sm text-muted-foreground">
                                Días
                            </dt>
                            <dd>{daysLine(request)}</dd>
                            <dt className="text-sm text-muted-foreground">
                                Le tocaba a
                            </dt>
                            <dd>
                                {request.manager ??
                                    'Nadie: no tiene jefe activo'}
                                {request.reason !== null && (
                                    <span className="block text-sm text-muted-foreground">
                                        {request.reason}
                                    </span>
                                )}
                            </dd>
                            {request.recordedByHr && (
                                <>
                                    <dt className="text-sm text-muted-foreground">
                                        Registro
                                    </dt>
                                    <dd>
                                        Lo registró RH a nombre de la persona
                                    </dd>
                                </>
                            )}
                            {request.note !== null && (
                                <>
                                    <dt className="text-sm text-muted-foreground">
                                        Nota
                                    </dt>
                                    <dd>{request.note}</dd>
                                </>
                            )}
                            {request.rejectionReason !== null && (
                                <>
                                    <dt className="text-sm text-muted-foreground">
                                        Motivo del rechazo
                                    </dt>
                                    <dd>{request.rejectionReason}</dd>
                                </>
                            )}
                        </dl>
                        <RequestActions request={request} />
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
