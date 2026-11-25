import { useState } from 'react';
import CorrectionSheet from '@/components/attendance/correction-sheet';
import PersonFrame from '@/components/attendance/person-frame';
import PunchFeedback from '@/components/attendance/punch-feedback';
import WeekActions from '@/components/attendance/week-actions';
import WeekDisputeSheet from '@/components/attendance/week-dispute-sheet';
import WeekGate from '@/components/attendance/week-gate';
import WeekQueue from '@/components/attendance/week-queue';
import WeekRead from '@/components/attendance/week-read';
import type { useWeek } from '@/hooks/use-week';
import { correctionTarget } from '@/lib/attendance/correction';
import type { CorrectionAsk } from '@/lib/attendance/correction';
import { weekActions, weekFeedback } from '@/lib/attendance/week-text';

/**
 * «Mi semana» (hallazgos 17, 21 y 22). Before the device verifies the person, a state screen centered with the way to open
 * it; after, the week, with «Confirmar mi semana» and «No estoy de acuerdo» at the foot. «Atrás» goes back to Marcar. A
 * correction and a dispute are asked in a sheet from the bottom, one at a time.
 */
export default function WeekScreen({
    company,
    punchUrl,
    state,
}: {
    company: string;
    punchUrl: string;
    state: ReturnType<typeof useWeek>;
}) {
    const [asking, setAsking] = useState<CorrectionAsk | null>(null);
    const [disputing, setDisputing] = useState(false);
    const { week, working } = state;
    const disputeUrl = week?.disputeUrl ?? null;

    return (
        <PersonFrame
            company={company}
            back={punchUrl}
            centered={week === null}
            actions={
                weekActions(week, false).length > 0 ? (
                    <WeekActions
                        week={week}
                        working={working}
                        queued={state.queue.pending}
                        onRead={() => void state.read()}
                        onConfirm={(url) => void state.confirm(url)}
                        onDispute={() => setDisputing(true)}
                    />
                ) : undefined
            }
        >
            <div className="flex w-full max-w-md flex-col items-center gap-4">
                <WeekQueue
                    pending={state.queue.pending}
                    notices={state.queue.notices}
                />
                {week === null ? (
                    <WeekGate />
                ) : (
                    <div className="flex w-full flex-col gap-4">
                        <WeekRead
                            week={week}
                            onAsk={(date, mark) =>
                                setAsking({ ...correctionTarget(mark), date })
                            }
                        />
                    </div>
                )}
                <div className="w-full empty:hidden">
                    <PunchFeedback
                        feedback={weekFeedback(state.error, state.notice)}
                    />
                </div>
            </div>
            <CorrectionSheet
                ask={asking}
                working={working !== null}
                onSend={(draft) =>
                    void state.correct(draft).then((sent) => {
                        if (sent) {
                            setAsking(null);
                        }
                    })
                }
                onClose={() => setAsking(null)}
            />
            <WeekDisputeSheet
                open={disputing && disputeUrl !== null}
                working={working !== null}
                onSend={(reason) =>
                    disputeUrl !== null &&
                    void state.dispute(disputeUrl, reason).then((done) => {
                        if (done) {
                            setDisputing(false);
                        }
                    })
                }
                onClose={() => setDisputing(false)}
            />
        </PersonFrame>
    );
}
