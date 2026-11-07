import { Head, Link } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import PersonFrame from '@/components/attendance/person-frame';
import PunchFeedback from '@/components/attendance/punch-feedback';
import WeekAnswer from '@/components/attendance/week-answer';
import WeekDays from '@/components/attendance/week-days';
import WeekGate from '@/components/attendance/week-gate';
import WeekHours from '@/components/attendance/week-hours';
import WeekNav from '@/components/attendance/week-nav';
import WeekRequests from '@/components/attendance/week-requests';
import { Button } from '@/components/ui/button';
import { useWeek } from '@/hooks/use-week';
import { weekFeedback, weekTitle } from '@/lib/attendance/week-text';

/**
 * «Mi semana» (criterios 5.2, 5.4 and 8.1): after their device verifies them, a person sees their marks and hours of one
 * week, why RH corrected a mark, and confirms it or says what is not right; they ask RH to correct a mark or to add one
 * they forgot, and see where each request stands.
 */
export default function Week({
    company,
    start,
    optionsUrl,
    weekUrl,
    punchUrl,
    correctionOptionsUrl,
    correctionUrl,
}: {
    company: string;
    start: string | null;
    optionsUrl: string;
    weekUrl: string;
    punchUrl: string;
    correctionOptionsUrl: string;
    correctionUrl: string;
}) {
    const { week, error, notice, working, read, confirm, dispute, correct } =
        useWeek({
            optionsUrl,
            weekUrl,
            start,
            correctionOptionsUrl,
            correctionUrl,
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
                        <WeekDays
                            days={week.days}
                            requests={week.requests}
                            working={working !== null}
                            onCorrect={correct}
                        />
                        <WeekRequests requests={week.requests} />
                        <WeekNav
                            previousUrl={week.previousUrl}
                            nextUrl={week.nextUrl}
                        />
                    </>
                )}
                <PunchFeedback feedback={weekFeedback(error, notice)} />
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
