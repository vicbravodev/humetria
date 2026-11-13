import CorrectionAsk from '@/components/attendance/correction-ask';
import CorrectionDecision from '@/components/attendance/correction-decision';
import CorrectionResolved from '@/components/attendance/correction-resolved';
import DayTimeline from '@/components/attendance/day-timeline';
import EffectList from '@/components/attendance/effect-list';
import Initials from '@/components/humetria/initials';
import { approveLines, rejectLines } from '@/lib/attendance/correction-effects';
import { markName } from '@/lib/attendance/next-step';
import { waitingFor } from '@/lib/attendance/corrections';
import type { CorrectionDetail as Detail, CorrectionStatus } from '@/types';

/**
 * The request in view (board A3): who asks what, that day in the record, what approving and rejecting would do, and the
 * decision, or how it ended. The effects come from the server before RH decides.
 */
export default function CorrectionDetail({
    detail,
    canResolve,
    pending,
    status,
    q,
}: {
    detail: Detail;
    canResolve: boolean;
    pending: number;
    status: CorrectionStatus;
    q: string;
}) {
    const adds = detail.previousAt === null;
    const open = detail.status === 'pending';

    return (
        <section
            id="correction-detail"
            aria-label="Detalle de la solicitud"
            className="flex min-w-0 scroll-mt-4 flex-col gap-6 card p-5 md:p-7"
        >
            <div className="flex items-center gap-3.5">
                <Initials name={detail.person.name} />
                <div className="flex min-w-0 flex-col">
                    <h2 className="text-xl leading-7 font-semibold text-balance">
                        {detail.person.name} pide{' '}
                        {adds ? 'agregar' : 'corregir'} su{' '}
                        {markName(detail.type).toLowerCase()}
                    </h2>
                    <span className="text-sm text-muted-foreground">
                        {detail.person.jobTitle} · Espera desde{' '}
                        {waitingFor(detail.ageDays)}
                    </span>
                </div>
            </div>
            <CorrectionAsk detail={detail} />
            {detail.day !== null && (
                <section className="flex flex-col gap-3">
                    <h3 className="text-base font-semibold">
                        Ese día en el registro
                    </h3>
                    <DayTimeline day={detail.day} />
                </section>
            )}
            {open && (
                <>
                    <section className="flex flex-col gap-3">
                        <h3 className="text-base font-semibold">
                            Si la apruebas
                        </h3>
                        <EffectList lines={approveLines(detail, canResolve)} />
                    </section>
                    <section className="flex flex-col gap-3">
                        <h3 className="text-base font-semibold">
                            Si la rechazas
                        </h3>
                        <ul className="flex flex-col gap-1.5 text-muted-foreground">
                            {rejectLines(detail).map((line) => (
                                <li key={line}>{line}</li>
                            ))}
                        </ul>
                    </section>
                    {canResolve ? (
                        <CorrectionDecision
                            key={detail.id}
                            detail={detail}
                            status={status}
                            q={q}
                        />
                    ) : (
                        <p className="border-t pt-5 text-sm text-muted-foreground">
                            Solo el dueño y RH resuelven las solicitudes.
                        </p>
                    )}
                </>
            )}
            {!open && (
                <CorrectionResolved detail={detail} pending={pending} q={q} />
            )}
        </section>
    );
}
