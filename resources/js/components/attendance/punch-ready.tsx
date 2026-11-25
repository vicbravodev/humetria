import { useState } from 'react';
import type { ReactNode } from 'react';
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
import PunchScanFirst from '@/components/attendance/punch-scan-first';
import PunchWeekLink from '@/components/attendance/punch-week-link';
import { clockNow } from '@/lib/attendance/correction';
import type { MarkType } from '@/lib/attendance/next-step';
import type { DayMark, PunchMode, Signer } from '@/lib/attendance/punch';
import type { PunchFeedback as Feedback } from '@/lib/attendance/punch-state';
import {
    dayStatus,
    firstName,
    greeting,
    needsScanFirst,
    nextAsksQr,
} from '@/lib/attendance/punch-view';
import { workerSlug } from '@/lib/pwa/sw-rules';
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
    qrTypes,
    weekUrl,
    enterUrl,
    timezone,
    now,
    readInApp,
    notes,
    onMark,
    onCode,
    onForget,
}: {
    company: string;
    person: Signer | null;
    marks: DayMark[] | null;
    feedback: Feedback | null;
    mode: PunchMode;
    scannedAt: string | null;
    /** The marks that ask for the code (the policy's); null when nobody is known. */
    qrTypes: string[] | null;
    weekUrl: string;
    enterUrl: string;
    timezone: string;
    /** The server's time now: the date and the greeting are the center's at that moment, never the phone's. */
    now: number;
    /** The code was read with the app's camera. */
    readInApp: boolean;
    /** What the screen says of the marks made without a connection: the queue, the ones not accepted, the readiness. */
    notes?: ReactNode;
    /** `scanned` is the code the camera just read for this mark. */
    onMark: (type: MarkType, scanned?: string) => void;
    /** The camera read the code of the screen of the person's center. */
    onCode: (code: string) => void;
    onForget: () => void;
}) {
    const today = clockNow(new Date(now), timezone);
    const [scanning, setScanning] = useState<MarkType | null>(null);
    const slug = workerSlug(location.pathname);
    const hasCode = readInApp || scannedAt !== null;
    // A policy that asks for the code on this mark: tapping it reads the screen's code first, and the key comes after.
    const start = (type: MarkType) =>
        slug !== null && needsScanFirst(qrTypes, type, hasCode)
            ? setScanning(type)
            : onMark(type);
    const asksQr = nextAsksQr(qrTypes, person?.next ?? null);

    return (
        <PersonFrame
            company={company}
            back={enterUrl}
            actions={
                <>
                    <PunchButton person={person} onMark={start} />
                    <PunchHint
                        mode={mode}
                        scannedAt={scannedAt}
                        readInApp={readInApp}
                        qr={asksQr}
                    />
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
                {notes}
                <PunchWeekLink href={weekUrl} />
                {marks !== null && <DayMarks marks={marks} />}
                {mode !== 'remote' && <LocationNote />}
                {person === null && <LinkDeviceNote enterUrl={enterUrl} />}
            </div>
            {scanning !== null && slug !== null && (
                <PunchScanFirst
                    type={scanning}
                    slug={slug}
                    onRead={(code, type) => {
                        setScanning(null);
                        onCode(code);
                        onMark(type, code);
                    }}
                    onClose={() => setScanning(null)}
                />
            )}
        </PersonFrame>
    );
}
