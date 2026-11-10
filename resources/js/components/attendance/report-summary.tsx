import ReportConfirmed from '@/components/attendance/report-confirmed';
import ReportFigure from '@/components/attendance/report-figure';
import { hoursHint } from '@/lib/attendance/report-findings';
import { duration } from '@/lib/attendance/week-text';
import type { ReportSummary as Summary } from '@/types';

/** The period at a glance (board A7), over every person-day of it: who, how many days, the hours and how much was confirmed. */
export default function ReportSummary({ summary }: { summary: Summary }) {
    const count = (value: number) => value.toLocaleString('es-MX');

    return (
        <section
            aria-label="El periodo en cifras"
            className="grid grid-cols-2 gap-3 md:grid-cols-4"
        >
            <ReportFigure
                label="Personas"
                value={count(summary.people)}
                hint="Con al menos un día en el periodo"
            />
            <ReportFigure
                label="Días con marcas"
                value={count(summary.days)}
                hint="Persona por persona"
            />
            <ReportFigure
                label="Horas trabajadas"
                value={duration(summary.workedMinutes)}
                hint={hoursHint(
                    summary.ordinaryMinutes,
                    summary.overtimeMinutes,
                )}
            />
            <ReportConfirmed
                percent={summary.confirmedPercent}
                confirmed={summary.confirmedDays}
                days={summary.days}
            />
        </section>
    );
}
