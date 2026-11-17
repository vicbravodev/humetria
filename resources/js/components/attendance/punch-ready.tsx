import DayMarks from '@/components/attendance/day-marks';
import DayStatusCard from '@/components/attendance/day-status-card';
import LinkDeviceNote from '@/components/attendance/link-device-note';
import LocationNote from '@/components/attendance/location-note';
import NotYou from '@/components/attendance/not-you';
import PersonFrame from '@/components/attendance/person-frame';
import PersonHeading from '@/components/humetria/person-heading';
import PunchButton from '@/components/attendance/punch-button';
import PunchFeedback from '@/components/attendance/punch-feedback';
import PunchHint from '@/components/attendance/punch-hint';
import PunchWeekLink from '@/components/attendance/punch-week-link';
import { clockNow } from '@/lib/attendance/correction';
import type { MarkType } from '@/lib/attendance/next-step';
import type { DayMark, PunchMode, Signer } from '@/lib/attendance/punch';
import type { PunchFeedback as Feedback } from '@/lib/attendance/punch-state';
import { dayStatus, firstName, greeting } from '@/lib/attendance/punch-view';
import { dayTitle } from '@/lib/attendance/week-text';

/**
 * Before marking, and between marks (board «Asistencia: marcar, acciones en 2×2»): the content first and the steps compact
 * at the foot. Who the screen speaks to and where their day stands, «Mi semana» where it is seen, why the position is asked
 * (not to a remote person: theirs is dropped) and how to get a key, to open. «Atrás» goes back to the portal.
 */
export default function PunchReady({
    company,
    person,
    marks,
    feedback,
    mode,
    scannedAt,
    weekUrl,
    enterUrl,
    timezone,
    now,
    onMark,
    onForget,
}: {
    company: string;
    person: Signer | null;
    marks: DayMark[] | null;
    feedback: Feedback | null;
    mode: PunchMode;
    scannedAt: string | null;
    weekUrl: string;
    enterUrl: string;
    timezone: string;
    /** The server's time now: the date and the greeting are the center's at that moment, never the phone's. */
    now: number;
    onMark: (type: MarkType) => void;
    onForget: () => void;
}) {
    const today = clockNow(new Date(now), timezone);

    return (
        <PersonFrame
            company={company}
            back={enterUrl}
            actions={
                <>
                    <PunchButton person={person} onMark={onMark} />
                    <PunchHint mode={mode} scannedAt={scannedAt} />
                </>
            }
        >
            <div className="flex w-full max-w-md flex-col gap-4">
                {person === null ? (
                    <PersonHeading title="Marcar su jornada">
                        Marque con su huella, su rostro o su PIN.
                    </PersonHeading>
                ) : (
                    <>
                        <PersonHeading
                            title={`${greeting(Number(today.time.slice(0, 2)))}, ${firstName(person.name)}`}
                        >
                            {dayTitle(today.date)}
                        </PersonHeading>
                        <NotYou onForget={onForget} />
                        <DayStatusCard status={dayStatus(person.next, marks)} />
                    </>
                )}
                <PunchFeedback feedback={feedback} />
                <PunchWeekLink href={weekUrl} />
                {marks !== null && <DayMarks marks={marks} />}
                {mode !== 'remote' && <LocationNote />}
                {person === null && <LinkDeviceNote enterUrl={enterUrl} />}
            </div>
        </PersonFrame>
    );
}
