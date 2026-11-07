import { Head, Link } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import PersonFrame from '@/components/attendance/person-frame';
import PunchFeedback from '@/components/attendance/punch-feedback';
import WeekAnswer from '@/components/attendance/week-answer';
import WeekDays from '@/components/attendance/week-days';
import WeekGate from '@/components/attendance/week-gate';
import WeekHours from '@/components/attendance/week-hours';
import WeekNav from '@/components/attendance/week-nav';
import { Button } from '@/components/ui/button';
import { useWeek } from '@/hooks/use-week';
import { weekTitle } from '@/lib/attendance/week-text';

/**
 * «Mi semana» (criterios 5.2 and 8.1): after their device verifies them, a person sees their marks and hours of one week
 * and confirms it or says what is not right.
 */
export default function Week({
    company,
    start,
    optionsUrl,
    weekUrl,
    punchUrl,
}: {
    company: string;
    start: string | null;
    optionsUrl: string;
    weekUrl: string;
    punchUrl: string;
}) {
    const { week, error, working, read, confirm, dispute } = useWeek({
        optionsUrl,
        weekUrl,
        start,
    });

    return (
        <PersonFrame company={company}>
            <Head title="Mi semana" />
            <div className="flex w-full max-w-md flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        Mi semana
                    </h1>
                    {week !== null && (
                        <p className="text-muted-foreground">
                            {week.name} ·{' '}
                            {weekTitle(week.week.start, week.week.end)}
                        </p>
                    )}
                </div>
                {week === null ? (
                    <WeekGate
                        working={working === 'read'}
                        onRead={() => void read()}
                    />
                ) : (
                    <>
                        <WeekAnswer
                            week={week}
                            working={working !== null}
                            onConfirm={(url) => void confirm(url)}
                            onDispute={dispute}
                        />
                        <WeekHours week={week} />
                        <WeekDays days={week.days} />
                        <WeekNav
                            previousUrl={week.previousUrl}
                            nextUrl={week.nextUrl}
                        />
                    </>
                )}
                <PunchFeedback
                    feedback={
                        error === null ? null : { kind: 'error', text: error }
                    }
                />
                <Button
                    asChild
                    variant="outline"
                    size="lg"
                    className="text-base"
                >
                    <Link href={punchUrl}>
                        <Clock aria-hidden className="size-5" />
                        Volver a Marcar
                    </Link>
                </Button>
            </div>
        </PersonFrame>
    );
}
