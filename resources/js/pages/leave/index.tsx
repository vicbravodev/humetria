import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { CalendarDays, CalendarOff, Users } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import AheadStrip from '@/components/leave/ahead-strip';
import FirstSteps from '@/components/leave/first-steps';
import SummaryDetails from '@/components/leave/summary-details';
import SummaryKpis from '@/components/leave/summary-kpis';
import SummaryNotice from '@/components/leave/summary-notice';
import ToAnswerSection from '@/components/leave/to-answer-section';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { dateFact, outLine } from '@/lib/leave/overview';
import { leaveTrail } from '@/lib/leave/trail';
import { countOf } from '@/lib/plural';
import { index as requests } from '@/routes/tenant/vacations/requests';
import type {
    LeaveAheadDay,
    LeaveQueueRequest,
    LeaveSummary,
    LeaveSummaryRow,
    LeaveSummaryView,
    Paginated,
} from '@/types';

/**
 * RH's overview of Vacaciones: what waits for RH first, as cards to answer; then the three numbers, the next fourteen
 * days and, below, the list behind each number, paged. A company that has not started sees its three first steps.
 */
export default function LeaveIndex({
    view,
    summary,
    firstTime,
    toAnswer,
    next14,
    counts,
    people,
}: {
    view: LeaveSummaryView;
    summary: LeaveSummary;
    firstTime: boolean;
    toAnswer: LeaveQueueRequest[];
    next14: LeaveAheadDay[];
    counts: Record<LeaveSummaryView, number>;
    people: Paginated<LeaveSummaryRow>;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({ breadcrumbs: leaveTrail('Resumen') });
    const today = next14[0]?.date ?? '';

    return (
        <>
            <Head title="Vacaciones" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Vacaciones"
                    facts={[
                        { icon: CalendarDays, text: dateFact(today) },
                        {
                            icon: Users,
                            text: countOf(
                                summary.people,
                                'persona',
                                'personas',
                            ),
                        },
                        { icon: CalendarOff, text: outLine(next14) },
                    ]}
                    description="Todo en días, nunca en dinero."
                >
                    {summary.escalated > 0 && (
                        <Button asChild className="max-sm:h-11 max-sm:w-full">
                            <Link href={requests.url(tenantSlug)}>
                                Responder solicitudes
                            </Link>
                        </Button>
                    )}
                </PageHeader>
                {firstTime ? (
                    <FirstSteps />
                ) : (
                    <>
                        <SummaryNotice summary={summary} />
                        <SummaryKpis summary={summary} />
                        <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                            <ToAnswerSection
                                summary={summary}
                                requests={toAnswer}
                                today={today}
                            />
                            <AheadStrip
                                days={next14}
                                windowsClosing={summary.windowsClosing}
                            />
                        </div>
                        <SummaryDetails
                            view={view}
                            counts={counts}
                            people={people}
                        />
                    </>
                )}
            </div>
        </>
    );
}
