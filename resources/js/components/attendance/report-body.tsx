import ReportFindings from '@/components/attendance/report-findings';
import ReportLongPeriod from '@/components/attendance/report-long-period';
import ReportPeople from '@/components/attendance/report-people';
import ReportStart from '@/components/attendance/report-start';
import ReportSummary from '@/components/attendance/report-summary';
import type { AttendanceReportProps } from '@/types';

/** What the report shows once the filters are set: only the CSV for a long period, the figures and days, or the way to begin. */
export default function ReportBody(props: AttendanceReportProps) {
    const { center, period, summary, people, exportUrl, longPeriod } = props;

    if (longPeriod !== null && exportUrl !== null) {
        return (
            <ReportLongPeriod
                days={longPeriod.days}
                maxOnScreen={longPeriod.maxOnScreen}
                exportUrl={exportUrl}
            />
        );
    }

    if (summary === null || people === null || center === null) {
        return <ReportStart />;
    }

    return (
        <>
            <ReportSummary summary={summary} />
            <ReportFindings summary={summary} center={center} period={period} />
            <ReportPeople
                people={people}
                summary={summary}
                filters={{ ...period, center: center.id, ...props.filters }}
            />
        </>
    );
}
