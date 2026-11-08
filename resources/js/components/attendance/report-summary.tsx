import ReportFigure from '@/components/attendance/report-figure';
import { summaryHint } from '@/lib/attendance/report';
import { duration } from '@/lib/attendance/week-text';
import type { ReportSummary as Summary } from '@/types';

/** The period at a glance, over every person-day of it: who and how much, how much was confirmed and what changed. */
export default function ReportSummary({ summary }: { summary: Summary }) {
    const count = (value: number) => value.toLocaleString('es-MX');

    return (
        <section
            aria-label="El periodo en cifras"
            className="grid grid-cols-2 gap-3 min-[1240px]:grid-cols-6 md:grid-cols-3"
        >
            <ReportFigure
                label="Personas"
                value={count(summary.people)}
                hint="Con al menos un día en el periodo"
            />
            <ReportFigure
                label="Días"
                value={count(summary.days)}
                hint="Días con marcas, persona por persona"
            />
            <ReportFigure
                label="Horas trabajadas"
                value={duration(summary.workedMinutes)}
                hint={summaryHint(summary.people, summary.days)}
            />
            <ReportFigure
                label="Confirmados"
                value={`${summary.confirmedPercent} %`}
                hint="De los días, con su semana confirmada"
            />
            <ReportFigure
                label="Con correcciones"
                value={count(summary.withCorrections)}
                hint="Días con una marca corregida o agregada"
            />
            <ReportFigure
                label="Sin confirmar"
                value={count(summary.unconfirmed)}
                hint="Días de semanas que nadie respondió"
                alert={summary.unconfirmed > 0}
            />
        </section>
    );
}
