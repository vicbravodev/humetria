import { Link } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import DayMarks from '@/components/attendance/day-marks';
import DayStatusCard from '@/components/attendance/day-status-card';
import LinkDeviceNote from '@/components/attendance/link-device-note';
import LocationNote from '@/components/attendance/location-note';
import NotYou from '@/components/attendance/not-you';
import PersonFrame from '@/components/attendance/person-frame';
import PersonHeading from '@/components/attendance/person-heading';
import PunchButton from '@/components/attendance/punch-button';
import PunchFeedback from '@/components/attendance/punch-feedback';
import PunchHint from '@/components/attendance/punch-hint';
import { Button } from '@/components/ui/button';
import { clockNow } from '@/lib/attendance/correction';
import { ANY_STEP, nextSteps } from '@/lib/attendance/next-step';
import type { MarkType } from '@/lib/attendance/next-step';
import type { Signer } from '@/lib/attendance/punch';
import type { PunchFeedback as Feedback } from '@/lib/attendance/punch-state';
import { dayStatus, firstName, greeting } from '@/lib/attendance/punch-view';
import { dayTitle } from '@/lib/attendance/week-text';

/**
 * Before marking, and between marks (board P2): who the screen speaks to and where their day stands, why the position is
 * asked, their day once they marked, and at the foot the big button of the next step. A mark that came out of order is
 * said here, with every step offered again. When nobody is known in this browser, every step is offered.
 */
export default function PunchReady({
    company,
    person,
    marks,
    feedback,
    scanned,
    weekUrl,
    timezone,
    now,
    onMark,
    onForget,
}: {
    company: string;
    person: Signer | null;
    marks: { type: string; time: string }[] | null;
    feedback: Feedback | null;
    scanned: boolean;
    weekUrl: string;
    timezone: string;
    /** The time of this device's clock: the date and the greeting are the company's at that moment. */
    now: number;
    onMark: (type: MarkType) => void;
    onForget: () => void;
}) {
    const { primary, others } = nextSteps(person?.next ?? ANY_STEP);
    const today = clockNow(new Date(now), timezone);

    return (
        <PersonFrame
            company={company}
            actions={
                <>
                    <PunchButton
                        primary={primary}
                        others={others}
                        onMark={onMark}
                    />
                    <PunchHint scanned={scanned} />
                </>
            }
        >
            <div className="flex w-full max-w-md flex-col gap-5">
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
                <LocationNote />
                {marks !== null && <DayMarks marks={marks} />}
                {person === null && <LinkDeviceNote />}
                <Button
                    asChild
                    variant="outline"
                    size="lg"
                    className="text-base"
                >
                    <Link href={weekUrl}>
                        <CalendarDays aria-hidden className="size-5" />
                        Mi semana
                    </Link>
                </Button>
            </div>
        </PersonFrame>
    );
}
