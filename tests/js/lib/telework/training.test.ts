import { describe, expect, it } from 'vite-plus/test';

import {
    attendeesText,
    standingTone,
    trainingLines,
} from '@/lib/telework/training';

describe('standingTone', () => {
    it('is calm when up to date, warns when due soon and blocks when overdue', () => {
        expect(standingTone('ok')).toBe('success');
        expect(standingTone('due_soon')).toBe('warning');
        expect(standingTone('overdue')).toBe('destructive');
    });
});

describe('attendeesText', () => {
    it('words one person and many', () => {
        expect(attendeesText(1)).toBe('1 persona');
        expect(attendeesText(12)).toBe('12 personas');
    });
});

describe('trainingLines', () => {
    const base = {
        lastTrainingOn: null,
        dueOn: '2027-02-03',
        status: 'ok',
        statusLabel: 'Al corriente',
    } as const;

    it('gives a deadline, not «Al corriente», to whoever has not trained yet and is within their year', () => {
        expect(trainingLines(base)).toEqual({
            last: 'Aún sin capacitación',
            due: 'tiene hasta el 3 de febrero de 2027',
            label: 'En plazo',
        });
        expect(
            trainingLines({
                ...base,
                status: 'due_soon',
                statusLabel: 'Vence pronto',
            }).label,
        ).toBe('Vence pronto');
    });

    it('says that the deadline passed for whoever never trained and is late', () => {
        expect(
            trainingLines({
                ...base,
                status: 'overdue',
                statusLabel: 'Vencida',
            }),
        ).toEqual({
            last: 'Sin capacitación',
            due: 'Venció el 3 de febrero de 2027',
            label: 'Vencida',
        });
    });

    it('keeps the last training and the due date of whoever trained', () => {
        expect(
            trainingLines({ ...base, lastTrainingOn: '2026-02-03' }),
        ).toEqual({
            last: 'Última: 3 de febrero de 2026',
            due: 'Vence el 3 de febrero de 2027',
            label: 'Al corriente',
        });
    });
});
