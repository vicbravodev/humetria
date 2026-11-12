import { Head, setLayoutProps } from '@inertiajs/react';
import { leaveTrail } from '@/lib/leave/trail';
import { CircleAlert } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import Notice from '@/components/humetria/notice';
import SummaryCards from '@/components/leave/summary-cards';
import SummaryDetails from '@/components/leave/summary-details';
import { countOf } from '@/lib/plural';
import type {
    LeaveSummary,
    LeaveSummaryRow,
    LeaveSummaryView,
    Paginated,
} from '@/types';

/**
 * RH's overview of Vacaciones: first what needs them (who is out, what waits, the windows about to close, the days owed),
 * then the list behind each number, paged.
 */
export default function LeaveIndex({
    view,
    summary,
    counts,
    people,
}: {
    view: LeaveSummaryView;
    summary: LeaveSummary;
    counts: Record<LeaveSummaryView, number>;
    people: Paginated<LeaveSummaryRow>;
}) {
    setLayoutProps({
        breadcrumbs: leaveTrail('Resumen'),
    });

    return (
        <>
            <Head title="Vacaciones" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Vacaciones"
                    title="Resumen"
                    description="Quién está fuera, qué espera respuesta y cuántos días siguen por disfrutar. Todo en días, nunca en dinero."
                />
                {summary.missingStartDate > 0 && (
                    <Notice>
                        <strong>
                            {countOf(
                                summary.missingStartDate,
                                'persona no tiene',
                                'personas no tienen',
                            )}{' '}
                            fecha de ingreso.
                        </strong>{' '}
                        Sin ella no se les otorgan días de vacaciones.
                    </Notice>
                )}
                {summary.statementsUnacknowledged > 0 && (
                    <p className="flex items-center gap-2 text-meta text-muted-foreground">
                        <CircleAlert aria-hidden className="size-4" />
                        {countOf(
                            summary.statementsUnacknowledged,
                            'constancia anual sin acuse',
                            'constancias anuales sin acuse',
                        )}
                        .
                    </p>
                )}
                <SummaryCards summary={summary} />
                <SummaryDetails view={view} counts={counts} people={people} />
            </div>
        </>
    );
}
