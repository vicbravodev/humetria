import { describe, expect, it } from 'vite-plus/test';

import {
    approveLines,
    dayTimeline,
    firstName,
    isBlocked,
    rejectLines,
} from '@/lib/attendance/correction-effects';
import type { CorrectionDetail } from '@/types';

const detail = (patch: Partial<CorrectionDetail> = {}): CorrectionDetail => ({
    id: 'r-1',
    person: { name: 'Bob Díaz', jobTitle: 'Almacenista' },
    type: 'start',
    requestedAt: '2026-11-08 09:00',
    previousAt: null,
    reason: 'Olvidé marcar mi entrada, el QR no cargaba.',
    ageDays: 0,
    status: 'pending',
    note: null,
    day: {
        date: '2026-11-08',
        marks: [],
        asked: { type: 'start', time: '09:00', nextDay: false },
    },
    effect: {
        sequenceOk: true,
        afterApproval: 'complete',
        reopensWeeks: [],
        noSchedule: false,
        restDay: false,
    },
    ...patch,
});

const withEffect = (
    patch: Partial<NonNullable<CorrectionDetail['effect']>>,
): CorrectionDetail => {
    const base = detail();

    return {
        ...base,
        effect: base.effect === null ? null : { ...base.effect, ...patch },
    };
};

describe('firstName', () => {
    it('is the first word of the name', () => {
        expect(firstName('Bob Díaz')).toBe('Bob');
        expect(firstName('Ing. Bob Díaz')).toBe('Bob');
        expect(firstName('Ana')).toBe('Ana');
    });
});

describe('approveLines', () => {
    it('leaves out the bitácora line for who cannot resolve: it would not be theirs', () => {
        const lines = approveLines(detail(), false);

        expect(lines.map(({ text }) => text)).not.toContain(
            'Queda en la bitácora a tu nombre, con tu nota.',
        );
        expect(lines).toHaveLength(2);
    });

    it('says a forgotten mark is added, with its evidence, and goes to the bitácora', () => {
        expect(approveLines(detail())).toEqual([
            {
                tone: 'done',
                text: 'Se agrega la marca de entrada de las 09:00 con evidencia Corregida. No hay marca original: es una marca agregada.',
            },
            {
                tone: 'done',
                text: 'Su semana aún no está confirmada: no se reabre nada.',
            },
            {
                tone: 'done',
                text: 'Queda en la bitácora a tu nombre, con tu nota.',
            },
        ]);
    });

    it('says a mark that was there moves, and that its original stays', () => {
        const [first] = approveLines(
            detail({
                type: 'end',
                previousAt: '2026-11-08 18:00',
                requestedAt: '2026-11-08 18:30',
            }),
        );

        expect(first).toEqual({
            tone: 'done',
            text: 'La marca de salida de las 18:00 pasa a las 18:30, con evidencia Corregida. La original se conserva y las dos se ven en el reporte.',
        });
    });

    it('names the week that reopens, or the weeks', () => {
        const one = approveLines(withEffect({ reopensWeeks: ['2026-11-02'] }));
        const two = approveLines(
            withEffect({ reopensWeeks: ['2026-11-02', '2026-11-09'] }),
        );

        expect(one[1]).toEqual({
            tone: 'done',
            text: 'Su semana del 2 al 8 de noviembre de 2026 estaba confirmada: se reabre y le pedimos confirmarla otra vez.',
        });
        expect(two[1]).toEqual({
            tone: 'done',
            text: 'Sus semanas del 2 al 8 de noviembre de 2026 y del 9 al 15 de noviembre de 2026 estaban confirmadas: se reabren y le pedimos confirmarlas otra vez.',
        });
    });

    it('warns that the day would still miss its end', () => {
        const lines = approveLines(
            withEffect({ afterApproval: 'missing_end' }),
        );

        expect(lines.at(-1)).toEqual({
            tone: 'warn',
            text: 'El día seguiría sin marca de salida hasta que Bob agregue la suya.',
        });
    });

    it('says how the day counts: no schedule, or a rest day', () => {
        const none = approveLines(withEffect({ noSchedule: true }));
        const rest = approveLines(withEffect({ restDay: true }));

        expect(none.at(-1)).toEqual({
            tone: 'info',
            text: 'Bob no tiene horario: se cuentan 8 h cada día y ningún día de descanso.',
        });
        expect(rest.at(-1)).toEqual({
            tone: 'info',
            text: 'Ese día es de descanso de Bob: ese tiempo se contaría aparte, como trabajo en descanso.',
        });
    });

    it('says the approval would be refused when the day is left out of order, and no more than that', () => {
        const lines = approveLines(
            withEffect({
                sequenceOk: false,
                afterApproval: 'missing_end',
                noSchedule: true,
            }),
        );

        expect(lines.filter((line) => line.tone === 'block')).toEqual([
            {
                tone: 'block',
                text: 'Con esa hora el día queda en desorden (una salida antes de su entrada o una entrada con otra abierta): no se podrá aprobar. Rechaza la solicitud o pide a Bob otra hora.',
            },
        ]);
        expect(lines.some((line) => line.tone === 'warn')).toBe(false);
    });

    it('has nothing to say of a request already resolved', () => {
        expect(approveLines(detail({ effect: null, day: null }))).toEqual([]);
    });
});

describe('isBlocked', () => {
    it('is only when the server says the approval would be refused', () => {
        expect(isBlocked(detail())).toBe(false);
        expect(isBlocked(withEffect({ sequenceOk: false }))).toBe(true);
        expect(isBlocked(detail({ effect: null }))).toBe(false);
    });
});

describe('rejectLines', () => {
    it('says the record does not change and that the note reaches the person', () => {
        expect(rejectLines(detail())).toEqual([
            'Su registro no cambia: queda como está.',
            'Bob verá tu nota en «Mi semana» y podrá pedir la corrección de nuevo.',
        ]);
    });
});

describe('dayTimeline', () => {
    const point = (type: string, time: string, nextDay = false) => ({
        type,
        time,
        nextDay,
    });

    it('draws a day from 06:00 to 22:00 with the mark asked placed on it', () => {
        const timeline = dayTimeline({
            date: '2026-11-08',
            marks: [],
            asked: point('start', '10:00'),
        });

        expect(timeline.ticks).toEqual([
            '06:00',
            '10:00',
            '14:00',
            '18:00',
            '22:00',
        ]);
        expect(timeline.asked).toEqual({
            type: 'start',
            percent: 25,
            label: '10:00',
        });
        expect(timeline.band).toBeNull();
    });

    it('shades from the first start to the last end of the day', () => {
        const timeline = dayTimeline({
            date: '2026-11-08',
            marks: [point('start', '08:00'), point('end', '18:00')],
            asked: point('end', '20:00'),
        });

        expect(timeline.marks.map((mark) => mark.percent)).toEqual([12.5, 75]);
        expect(timeline.band).toEqual({ left: 12.5, width: 62.5 });
    });

    it('does not shade a day that has a start and no end', () => {
        expect(
            dayTimeline({
                date: '2026-11-08',
                marks: [point('start', '08:00')],
                asked: point('end', '20:00'),
            }).band,
        ).toBeNull();
    });

    it('widens to the whole hour around a mark outside 06:00 to 22:00, and past midnight', () => {
        const timeline = dayTimeline({
            date: '2026-11-08',
            marks: [point('start', '05:30')],
            asked: point('end', '02:15', true),
        });

        expect(timeline.ticks[0]).toBe('05:00');
        expect(timeline.ticks[4]).toBe('03:00');
        expect(timeline.asked.label).toBe('02:15');
        expect(timeline.asked.percent).toBeGreaterThan(90);
        expect(timeline.asked.percent).toBeLessThan(100);
    });
});
