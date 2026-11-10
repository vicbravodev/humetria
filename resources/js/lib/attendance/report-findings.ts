import { duration } from '@/lib/attendance/week-text';
import type { ReportShow, ReportSummary } from '@/types';

/** Something that makes the report look weak before an inspection, and the part of the list that shows it. */
export type Finding = {
    key: 'unconfirmed' | 'over-twelve' | 'without-end';
    title: string;
    text: string;
    action: string;
    show: Exclude<ReportShow, 'all'>;
};

const people = (count: number): string =>
    count === 1 ? 'Ver a 1 persona' : `Ver a las ${count} personas`;

/**
 * «Antes de mostrarlo en una inspección» (board A7): the days a reader of the report would stop at, from the figures of
 * all the period. With none the report is ready and the list is empty.
 */
export function inspectionFindings(summary: ReportSummary): Finding[] {
    const findings: (Finding | null)[] = [
        summary.unconfirmed === 0
            ? null
            : {
                  key: 'unconfirmed',
                  title:
                      summary.unconfirmed === 1
                          ? '1 día de una semana que nadie respondió'
                          : `${summary.unconfirmed} días de semanas que nadie respondió`,
                  text: 'Sin confirmar no es conformidad: conviene que cada persona la revise.',
                  action: people(summary.unconfirmedPeople),
                  show: 'unconfirmed',
              },
        summary.overTwelveDays === 0
            ? null
            : {
                  key: 'over-twelve',
                  title:
                      summary.overTwelveDays === 1
                          ? '1 día con más de 12 horas de trabajo'
                          : `${summary.overTwelveDays} días con más de 12 horas de trabajo`,
                  text: 'La jornada no pasa de 12 horas (art. 68): revisa que las marcas estén bien antes de entregarlo.',
                  action: people(summary.flaggedPeople),
                  show: 'flagged',
              },
        summary.withoutEndDays === 0
            ? null
            : {
                  key: 'without-end',
                  title:
                      summary.withoutEndDays === 1
                          ? '1 día sin marca de fin'
                          : `${summary.withoutEndDays} días sin marca de fin`,
                  text: 'Un día sin fin no suma horas: la persona o RH pueden corregirlo.',
                  action: people(summary.flaggedPeople),
                  show: 'flagged',
              },
    ];

    return findings.filter((finding) => finding !== null);
}

/** «96 h ordinarias · 23 h extra»: what the hours worked are made of. */
export function hoursHint(ordinary: number, overtime: number): string {
    if (ordinary === 0 && overtime === 0) {
        return 'Sin horas trabajadas';
    }

    return `${duration(ordinary)} ordinarias · ${overtime === 0 ? 'sin extra' : `${duration(overtime)} extra`}`;
}

/** «5 de 13 días». */
export function confirmedHint(confirmed: number, days: number): string {
    return days === 0
        ? 'Sin días en el periodo'
        : `${confirmed} de ${days} días`;
}
