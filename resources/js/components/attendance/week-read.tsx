import WeekDays from '@/components/attendance/week-days';
import WeekHeading from '@/components/attendance/week-heading';
import WeekNav from '@/components/attendance/week-nav';
import WeekRequests from '@/components/attendance/week-requests';
import WeekTotal from '@/components/attendance/week-total';
import WeekUncoveredDays from '@/components/attendance/week-uncovered-days';
import type { WeekView, WeekMark } from '@/lib/attendance/week';

/**
 * The week the signature opened (board «Asistencia: su semana»): where it stands beside the title, the bar of the week,
 * the total, the days they still have to cover, the days folded, and what the person asked RH. Deciding goes at the foot.
 */
export default function WeekRead({
    week,
    onAsk,
}: {
    week: WeekView;
    onAsk: (date: string, mark: WeekMark | null) => void;
}) {
    return (
        <>
            <WeekHeading week={week} />
            <WeekNav
                start={week.week.start}
                end={week.week.end}
                previousUrl={week.previousUrl}
                nextUrl={week.nextUrl}
            />
            <WeekTotal week={week} />
            <WeekUncoveredDays
                dates={week.uncoveredDays}
                onAsk={(date) => onAsk(date, null)}
            />
            <WeekDays days={week.days} requests={week.requests} onAsk={onAsk} />
            <WeekRequests requests={week.requests} />
        </>
    );
}
