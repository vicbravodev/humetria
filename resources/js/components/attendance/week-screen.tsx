import { Link } from '@inertiajs/react';
import { CircleCheck, Clock } from 'lucide-react';
import { useState } from 'react';
import PersonFrame from '@/components/attendance/person-frame';
import PersonHeading from '@/components/attendance/person-heading';
import PunchFeedback from '@/components/attendance/punch-feedback';
import ToneTile from '@/components/attendance/tone-tile';
import WeekActions from '@/components/attendance/week-actions';
import WeekAnswer from '@/components/attendance/week-answer';
import WeekDays from '@/components/attendance/week-days';
import WeekGate from '@/components/attendance/week-gate';
import WeekHours from '@/components/attendance/week-hours';
import WeekNav from '@/components/attendance/week-nav';
import WeekRequests from '@/components/attendance/week-requests';
import WeekTotal from '@/components/attendance/week-total';
import { Button } from '@/components/ui/button';
import type { useWeek } from '@/hooks/use-week';
import {
    weekActions,
    weekFeedback,
    weekHeading,
} from '@/lib/attendance/week-text';

/**
 * «Mi semana» (board P4): the total and the shape of the week first, then where it stands, the hours as the law pays them and
 * every day; deciding goes at the foot, where the thumb is. Before the device verifies the person, only the way to open it.
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
    const [disputing, setDisputing] = useState(false);
    const { week, working } = state;
    const open = weekActions(week, disputing).length > 0;
    const heading =
        week === null ? { title: 'Mi semana', text: null } : weekHeading(week);

    return (
        <PersonFrame
            company={company}
            actions={
                open ? (
                    <WeekActions
                        week={week}
                        disputing={disputing}
                        working={working}
                        onRead={() => void state.read()}
                        onConfirm={(url) => void state.confirm(url)}
                        onDispute={() => setDisputing(true)}
                    />
                ) : undefined
            }
        >
            <div className="flex w-full max-w-md flex-col gap-5">
                {week?.status === 'confirmed' && (
                    <ToneTile icon={CircleCheck} tone="ok" large />
                )}
                <PersonHeading title={heading.title}>
                    {heading.text}
                </PersonHeading>
                {week === null ? (
                    <WeekGate />
                ) : (
                    <>
                        <WeekTotal week={week} />
                        <WeekAnswer
                            week={week}
                            disputing={disputing}
                            working={working !== null}
                            onCloseDispute={() => setDisputing(false)}
                            onDispute={state.dispute}
                        />
                        <WeekHours week={week} />
                        <WeekDays
                            days={week.days}
                            requests={week.requests}
                            working={working !== null}
                            onCorrect={state.correct}
                        />
                        <WeekRequests requests={week.requests} />
                        <WeekNav
                            previousUrl={week.previousUrl}
                            nextUrl={week.nextUrl}
                        />
                    </>
                )}
                <PunchFeedback
                    feedback={weekFeedback(state.error, state.notice)}
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
