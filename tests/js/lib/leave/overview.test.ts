import { describe, expect, it } from 'vitest';
import {
    coverageLine,
    dateFact,
    daysUntil,
    outLine,
    readingLine,
    reasonLine,
    startsIn,
    stripDay,
} from '@/lib/leave/overview';

const ahead = (counts: number[]) =>
    counts.map((away, index) => ({
        date: `2026-11-${String(11 + index).padStart(2, '0')}`,
        away,
    }));

describe('readingLine', () => {
    it('says they are up to date when nothing waits', () => {
        expect(readingLine(0, null, '2026-11-11')).toBe(
            'Estás al día. Nadie espera respuesta.',
        );
    });

    it('says how many wait and when the closest one starts, in the right number', () => {
        expect(readingLine(4, '2026-11-18', '2026-11-11')).toBe(
            '4 solicitudes esperan tu respuesta. La más próxima empieza el 18 de noviembre.',
        );
        expect(readingLine(1, '2026-12-02', '2026-11-11')).toBe(
            '1 solicitud espera tu respuesta. La más próxima empieza el 2 de diciembre.',
        );
    });

    it('leaves the date out when it is not known', () => {
        expect(readingLine(2, null, '2026-11-11')).toBe(
            '2 solicitudes esperan tu respuesta.',
        );
    });
});

describe('daysUntil', () => {
    it('counts the whole days from today to the day', () => {
        expect(daysUntil('2026-11-18', '2026-11-11')).toBe(7);
        expect(daysUntil('2026-11-11', '2026-11-11')).toBe(0);
        expect(daysUntil('2026-11-10', '2026-11-11')).toBe(-1);
    });

    it('is nothing for a text that is not a date', () => {
        expect(daysUntil('nope', '2026-11-11')).toBeNull();
    });

    it('does not drift with the change of the clocks', () => {
        expect(daysUntil('2026-11-02', '2026-10-24')).toBe(9);
    });
});

describe('startsIn', () => {
    it.each([
        ['2026-11-11', 'Empieza hoy'],
        ['2026-11-12', 'Empieza mañana'],
        ['2026-11-18', 'Empieza en 7 días'],
    ])('says when %s starts', (startsOn, text) => {
        expect(startsIn(startsOn, '2026-11-11')).toBe(text);
    });

    it('says nothing of what already started or when a date is wrong', () => {
        expect(startsIn('2026-11-10', '2026-11-11')).toBeNull();
        expect(startsIn('nope', '2026-11-11')).toBeNull();
    });
});

describe('reasonLine', () => {
    it('names the manager who did not answer', () => {
        expect(
            reasonLine(
                'Su jefe no respondió en 3 días hábiles',
                'Dña Helena Guillen',
            ),
        ).toBe('Su jefe (Dña Helena Guillen) no respondió en 3 días hábiles');
    });

    it('keeps the reason as it is with nobody to name', () => {
        expect(reasonLine('Sin jefe activo', null)).toBe('Sin jefe activo');
        expect(reasonLine('Su jefe no respondió en 3 días hábiles', null)).toBe(
            'Su jefe no respondió en 3 días hábiles',
        );
        expect(reasonLine(null, 'Ana')).toBe('');
    });
});

describe('coverageLine', () => {
    it('says the days cover it and how many would be left', () => {
        expect(coverageLine({ fits: true, leftAfter: 20 })).toBe(
            'Sí le alcanza · le quedarían 20 días',
        );
        expect(coverageLine({ fits: true, leftAfter: 1 })).toBe(
            'Sí le alcanza · le quedaría 1 día',
        );
        expect(coverageLine({ fits: true, leftAfter: 0 })).toBe(
            'Sí le alcanza · no le quedarían días',
        );
    });

    it('says how many are missing when they do not cover it', () => {
        expect(coverageLine({ fits: false, leftAfter: -3 })).toBe(
            'No le alcanza · le faltan 3 días',
        );
        expect(coverageLine({ fits: false, leftAfter: -1 })).toBe(
            'No le alcanza · le falta 1 día',
        );
    });

    it('says nothing of a leave, which uses no days', () => {
        expect(coverageLine(null)).toBe('');
    });
});

describe('outLine', () => {
    it('says nobody is out today nor this week', () => {
        expect(outLine(ahead(Array(14).fill(0)))).toBe(
            'Nadie está fuera hoy ni esta semana',
        );
    });

    it('counts who is out today', () => {
        expect(outLine(ahead([1, 0, 0, 0, 0, 0, 0, 0]))).toBe(
            '1 persona fuera hoy',
        );
        expect(outLine(ahead([3, 0, 0, 0, 0, 0, 0, 0]))).toBe(
            '3 personas fuera hoy',
        );
    });

    it('says who is out the next days when nobody is today', () => {
        expect(outLine(ahead([0, 0, 2, 0, 0, 0, 0, 5]))).toBe(
            'Nadie está fuera hoy · hasta 2 en los próximos 7 días',
        );
    });
});

describe('stripDay', () => {
    it('gives the weekday and the number of a day', () => {
        expect(stripDay('2026-11-11')).toEqual({
            weekday: 'mié',
            number: '11',
        });
        expect(stripDay('2026-11-14')).toEqual({
            weekday: 'sáb',
            number: '14',
        });
    });

    it('gives nothing readable for a wrong date', () => {
        expect(stripDay('nope')).toEqual({ weekday: '', number: '' });
    });
});

describe('dateFact', () => {
    it('writes the day with its weekday', () => {
        expect(dateFact('2026-11-11')).toBe('Miércoles 11 de noviembre');
    });

    it('is empty for a wrong date', () => {
        expect(dateFact('nope')).toBe('');
    });
});
