import { Head } from '@inertiajs/react';
import DayMarks from '@/components/attendance/day-marks';
import LinkDeviceNote from '@/components/attendance/link-device-note';
import LocationNote from '@/components/attendance/location-note';
import PersonFrame from '@/components/attendance/person-frame';
import PunchButton from '@/components/attendance/punch-button';
import PunchFeedback from '@/components/attendance/punch-feedback';
import { usePunch } from '@/hooks/use-punch';
import { nextSteps, ANY_STEP } from '@/lib/attendance/next-step';

type Person = {
    name: string;
    marks: { type: string; time: string }[];
    next: string[];
};

/**
 * A person marks their working day from their linked device (no account): the big button of the next step, their day,
 * and why the position is asked for. Before a first mark in this browser, nobody is known: every step is offered.
 */
export default function Punch({
    company,
    qr,
    person,
    askLocation,
    optionsUrl,
    punchUrl,
}: {
    company: string;
    qr: string | null;
    person: Person | null;
    askLocation: boolean;
    optionsUrl: string;
    punchUrl: string;
}) {
    const { day, working, feedback, mark } = usePunch({
        optionsUrl,
        punchUrl,
        qr,
        askLocation,
        initial:
            person === null
                ? null
                : { marks: person.marks, next: person.next, flag: null },
    });
    const { primary, others } = nextSteps(day?.next ?? ANY_STEP);

    return (
        <PersonFrame company={company}>
            <Head title="Marcar" />
            <div className="flex w-full max-w-md flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        {person === null
                            ? 'Marcar su jornada'
                            : `Hola, ${person.name}`}
                    </h1>
                    <p className="text-muted-foreground">
                        Marque con su huella, su rostro o su PIN.
                    </p>
                </div>
                <PunchButton
                    primary={primary}
                    others={others}
                    working={working}
                    onMark={(type) => void mark(type)}
                />
                <PunchFeedback feedback={feedback} />
                {askLocation && <LocationNote />}
                {day === null ? (
                    <LinkDeviceNote />
                ) : (
                    <DayMarks marks={day.marks} />
                )}
            </div>
        </PersonFrame>
    );
}
