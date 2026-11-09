import { describe, expect, it } from 'vitest';
import {
    confirmedHint,
    hoursHint,
    inspectionFindings,
} from '@/lib/attendance/report-findings';
import type { ReportSummary } from '@/types';

const summary = (overrides: Partial<ReportSummary> = {}): ReportSummary => ({
    people: 5,
    days: 13,
    workedMinutes: 7140,
    confirmedPercent: 38,
    withCorrections: 0,
    unconfirmed: 0,
    confirmedDays: 13,
    ordinaryMinutes: 5760,
    overtimeMinutes: 1380,
    overTwelveDays: 0,
    withoutEndDays: 0,
    flaggedPeople: 0,
    unconfirmedPeople: 0,
    ...overrides,
});

describe('inspectionFindings', () => {
    it('has nothing to say about a report that is ready', () => {
        expect(inspectionFindings(summary())).toEqual([]);
    });

    it('says that silence is no conformity, with the people to see', () => {
        expect(
            inspectionFindings(
                summary({ unconfirmed: 3, unconfirmedPeople: 2 }),
            ),
        ).toEqual([
            {
                key: 'unconfirmed',
                title: '3 días de semanas que nadie respondió',
                text: 'Sin confirmar no es conformidad: conviene que cada persona la revise.',
                action: 'Ver a las 2 personas',
                show: 'unconfirmed',
            },
        ]);
    });

    it('counts the days over 12 hours (art. 68) and the days with no end, in singular and plural', () => {
        const findings = inspectionFindings(
            summary({
                overTwelveDays: 2,
                withoutEndDays: 1,
                flaggedPeople: 3,
            }),
        );

        expect(findings.map((finding) => finding.title)).toEqual([
            '2 días con más de 12 horas de trabajo',
            '1 día sin marca de fin',
        ]);
        expect(findings.map((finding) => finding.show)).toEqual([
            'flagged',
            'flagged',
        ]);
        expect(findings[0]?.text).toContain('art. 68');
        expect(findings[1]?.text).toContain('no suma horas');
        expect(findings[0]?.action).toBe('Ver a las 3 personas');
    });

    it('says «Ver a 1 persona» for one', () => {
        const [finding] = inspectionFindings(
            summary({ unconfirmed: 1, unconfirmedPeople: 1 }),
        );

        expect(finding?.title).toBe('1 día de una semana que nadie respondió');
        expect(finding?.action).toBe('Ver a 1 persona');
    });
});

describe('hoursHint', () => {
    it('splits the hours worked into ordinary and extra', () => {
        expect(hoursHint(5760, 1380)).toBe('96 h ordinarias · 23 h extra');
        expect(hoursHint(480, 0)).toBe('8 h ordinarias · sin extra');
        expect(hoursHint(0, 0)).toBe('Sin horas trabajadas');
    });
});

describe('confirmedHint', () => {
    it('counts the confirmed days of all', () => {
        expect(confirmedHint(5, 13)).toBe('5 de 13 días');
        expect(confirmedHint(0, 0)).toBe('Sin días en el periodo');
    });
});
