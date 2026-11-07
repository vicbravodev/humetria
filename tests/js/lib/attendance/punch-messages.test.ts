import { describe, expect, it } from 'vitest';
import { doneMessage, punchMessage } from '@/lib/attendance/punch';

const day = {
    marks: [{ type: 'start', time: '09:00' }],
    flag: null,
};

describe('punchMessage', () => {
    it.each([
        'outside_zone',
        'accuracy_too_low',
        'no_location',
        'qr_required',
        'qr_invalid',
    ])('says what to do about %s, and to ask RH for a correction', (reason) => {
        expect(punchMessage(reason)).toContain('Recursos Humanos');
    });

    it.each([
        ['day_already_open', 'Ya marcó su entrada'],
        ['day_not_open', 'Primero marque su entrada'],
        ['already_on_break', 'Ya marcó su salida a comer'],
        ['not_on_break', 'Aún no marca su salida a comer'],
        ['not_verified', 'pida a Recursos Humanos'],
        ['cancelled', 'huella'],
        ['throttled', 'Espere un minuto'],
        ['unsupported', 'navegador'],
        ['failed', 'Intente de nuevo'],
        ['anything', 'Intente de nuevo'],
    ])('words %s', (reason, text) => {
        expect(punchMessage(reason)).toContain(text);
    });
});

describe('doneMessage', () => {
    it('confirms the mark with its time', () => {
        expect(doneMessage(day)).toBe(
            'Listo: se registró su entrada a las 09:00.',
        );
    });

    it('tells the person a mark out of zone is flagged for RH', () => {
        expect(doneMessage({ ...day, flag: 'outside_zone' })).toBe(
            'Listo: se registró su entrada a las 09:00. Quedó fuera de zona y Recursos Humanos la verá señalada.',
        );
    });

    it('tells the person a mark with no location is flagged for RH', () => {
        expect(doneMessage({ ...day, flag: 'no_location' })).toBe(
            'Listo: se registró su entrada a las 09:00. Quedó sin ubicación y Recursos Humanos la verá señalada.',
        );
    });

    it('says nothing of a flag only the panel reads, nor of a day with no marks', () => {
        expect(doneMessage({ ...day, flag: 'center_without_zones' })).toBe(
            'Listo: se registró su entrada a las 09:00.',
        );
        expect(doneMessage({ marks: [], flag: null })).toBe('Listo.');
    });
});

describe('doneMessage of each mark', () => {
    it.each([
        ['break_start', 'Listo: se registró su salida a comer a las 13:00.'],
        ['break_end', 'Listo: se registró su regreso de comer a las 13:00.'],
        ['end', 'Listo: se registró su salida a las 13:00.'],
    ])('names %s', (type, text) => {
        expect(
            doneMessage({
                marks: [{ type, time: '13:00' }],
                flag: null,
            }),
        ).toBe(text);
    });
});
