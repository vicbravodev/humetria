import { Head, Link } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import DayMarks from '@/components/attendance/day-marks';
import LinkDeviceNote from '@/components/attendance/link-device-note';
import LocationNote from '@/components/attendance/location-note';
import NotYou from '@/components/attendance/not-you';
import PersonFrame from '@/components/attendance/person-frame';
import PunchButton from '@/components/attendance/punch-button';
import PunchFeedback from '@/components/attendance/punch-feedback';
import { Button } from '@/components/ui/button';
import { usePunch } from '@/hooks/use-punch';
import { ANY_STEP, nextSteps } from '@/lib/attendance/next-step';
import type { Signer } from '@/lib/attendance/punch';

/**
 * A person marks their working day from their linked device (no account): the big button of the next step, why the
 * position is asked for, and their day once they mark. When nobody is known in this browser, every step is offered.
 */
export default function Punch({
    company,
    qr,
    person: remembered,
    optionsUrl,
    punchUrl,
    forgetUrl,
    weekUrl,
}: {
    company: string;
    qr: string | null;
    person: Signer | null;
    optionsUrl: string;
    punchUrl: string;
    forgetUrl: string;
    weekUrl: string;
}) {
    const { person, marks, feedback, working, mark, forget } = usePunch({
        optionsUrl,
        punchUrl,
        forgetUrl,
        qr,
        initial: remembered,
    });
    const { primary, others } = nextSteps(person?.next ?? ANY_STEP);

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
                    {person !== null && <NotYou onForget={forget} />}
                </div>
                <PunchButton
                    primary={primary}
                    others={others}
                    working={working}
                    onMark={(type) => void mark(type)}
                />
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
