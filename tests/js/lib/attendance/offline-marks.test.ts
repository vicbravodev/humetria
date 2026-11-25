import { describe, expect, it } from 'vitest';
import {
    badgeText,
    correctedAt,
    detailHours,
    flagText,
    rejectionText,
    reviewBadge,
    selectionPayload,
    signerText,
} from '@/lib/attendance/offline-marks';
import type { OfflineMarkDetail } from '@/types';

describe('flagText', () => {
    it.each([
        ['wide_interval', 'Más de 4 h sin conexión'],
        ['old_window', 'QR de más de 30 min antes'],
        ['no_position', 'Sin posición'],
        ['outside_zone', 'Fuera de la zona del centro'],
        ['zones_not_configured', 'El centro no tiene zonas configuradas'],
        ['declared_adjusted', 'La hora contada no es la declarada'],
    ])('says %s in words', (flag, text) => {
        expect(flagText(flag)).toBe(text);
    });

    it('does not show a flag it does not know as the code that it is', () => {
        expect(flagText('new_flag')).toBe('Otro aviso');
    });
});

describe('rejectionText', () => {
    it.each([
        ['ticket_expired', 'Su boleto ya había vencido'],
        ['person_inactive', 'La persona ya no estaba activa'],
        ['agreement_not_accepted', 'No había aceptado el acuerdo vigente'],
        ['sequence_conflict', 'La marca no cabía en el orden de su día'],
        ['wrong_center', 'Marcó en un centro que no es el suyo'],
        ['pass_expired', 'Su pase de la app había vencido'],
    ])('says %s in words', (reason, text) => {
        expect(rejectionText(reason)).toBe(text);
    });

    it('says a reason it does not know, and a missing one, without the code', () => {
        expect(rejectionText('other')).toBe('Sin motivo guardado');
        expect(rejectionText(null)).toBe('Sin motivo guardado');
    });
});

describe('badgeText', () => {
    it('says what the tab holds', () => {
        expect(badgeText('offline')).toBe('Sin conexión · por verificar');
        expect(badgeText('not_accepted')).toBe('Sin conexión · no aceptada');
    });
});

describe('signerText', () => {
    it('says which kind of key signed the mark, never which one', () => {
        expect(signerText('passkey')).toBe('Firmada con su llave de acceso');
        expect(signerText('device_key')).toBe(
            'Firmada con la llave del dispositivo',
        );
        expect(signerText('x')).toBeNull();
    });
});

describe('selectionPayload', () => {
    it('leaves out the empty and the repeated, in order', () => {
        expect(selectionPayload(['a', '', 'b', 'a', ' ', 'c'])).toEqual({
            ids: ['a', 'b', 'c'],
        });
    });

    it('takes at most the 25 of a page', () => {
        const ids = Array.from({ length: 30 }, (_, index) => `id-${index}`);

        expect(selectionPayload(ids).ids).toHaveLength(25);
        expect(selectionPayload(ids).ids[24]).toBe('id-24');
    });
});

const detail = (
    overrides: Partial<OfflineMarkDetail> = {},
): OfflineMarkDetail => ({
    id: 'm1',
    person: { name: 'Ana López', jobTitle: 'Operadora' },
    type: 'start',
    countedAt: '2026-11-09 09:00',
    basis: 'declared_interval',
    flags: [],
    ageDays: 2,
    declaredAt: '2026-11-09 09:00',
    ticketIssuedAt: '2026-11-09 07:00',
    windowStart: null,
    receivedAt: '2026-11-09 19:00',
    phoneClockAt: null,
    signer: 'passkey',
    justification: null,
    proposedAt: '2026-11-09 09:00',
    ...overrides,
});

describe('detailHours', () => {
    it('names every hour the server proved or was told', () => {
        expect(detailHours(detail())).toEqual([
            { label: 'Hora declarada', value: '9 nov · 09:00' },
            { label: 'Hora contada', value: '9 nov · 09:00' },
            { label: 'Sin conexión desde', value: '9 nov · 07:00 (boleto)' },
            { label: 'Llegó', value: '9 nov · 19:00' },
        ]);
    });

    it('adds the window of the QR and the clock of the phone, said as a hint, only when they came', () => {
        expect(
            detailHours(
                detail({
                    windowStart: '2026-11-09 08:55',
                    phoneClockAt: '2026-11-09 19:05',
                }),
            ),
        ).toEqual([
            { label: 'Hora declarada', value: '9 nov · 09:00' },
            { label: 'Hora contada', value: '9 nov · 09:00' },
            { label: 'Sin conexión desde', value: '9 nov · 07:00 (boleto)' },
            { label: 'No antes de', value: '9 nov · 08:55 (QR del centro)' },
            { label: 'Llegó', value: '9 nov · 19:00' },
            {
                label: 'Reloj del teléfono',
                value: '9 nov · 19:05 · no es prueba',
            },
        ]);
    });
});

describe('reviewBadge', () => {
    it('says nothing of a mark made online', () => {
        expect(reviewBadge({ offline: false, review: null })).toBeNull();
    });

    it('says a mark made without a connection waits for RH until it is reviewed, with a QR or without, even when the server named no review', () => {
        const waiting = {
            label: 'Sin conexión · por verificar',
            variant: 'warning',
        };

        expect(reviewBadge({ offline: true, review: 'pending' })).toEqual(
            waiting,
        );
        expect(reviewBadge({ offline: true, review: null })).toEqual(waiting);
    });

    it('says RH verified it, or said it was incorrect', () => {
        expect(reviewBadge({ offline: true, review: 'verified' })).toEqual({
            label: 'Verificada',
            variant: 'success',
        });
        expect(reviewBadge({ offline: true, review: 'incorrect' })).toEqual({
            label: 'RH la marcó incorrecta',
            variant: 'secondary',
        });
    });
});

describe('correctedAt', () => {
    it('writes the time RH gave on the day of the mark, as the server reads it', () => {
        expect(correctedAt('2026-11-09 09:00', '09:20', false)).toBe(
            '2026-11-09 09:20',
        );
    });

    it('writes nothing to void the mark, or when there is no time', () => {
        expect(correctedAt('2026-11-09 09:00', '09:20', true)).toBe('');
        expect(correctedAt('2026-11-09 09:00', '', false)).toBe('');
    });
});
