import { describe, expect, it } from 'vite-plus/test';

import {
    deviceKind,
    deviceStatus,
    deviceUse,
    panelListQuery,
} from '@/lib/attendance/devices';

describe('the address of a list of the registro', () => {
    it('carries only what differs from the default view', () => {
        expect(panelListQuery({ q: '  ', status: 'all', center: '' })).toEqual(
            {},
        );
        expect(
            panelListQuery({ q: ' Ana ', status: 'unlinked', center: 'c-1' }),
        ).toEqual({ q: 'Ana', status: 'unlinked', center: 'c-1' });
    });
});

describe('a linked device', () => {
    it('says plainly where a synced key lives, and when a key is of one device only', () => {
        expect(deviceKind(true)).toEqual({
            label: 'Llave sincronizada',
            hint: 'Vive en la cuenta de llaves de acceso de la persona (iCloud, Google): puede usarse desde otro equipo de esa cuenta, no solo desde este teléfono.',
        });
        expect(deviceKind(false)).toEqual({
            label: 'Solo este dispositivo',
            hint: 'La llave no sale de este equipo: para marcar desde otro, la persona necesita un enlace nuevo.',
        });
    });

    it('tells when it was linked, last used or revoked', () => {
        expect(
            deviceUse({
                enrolledAt: '2026-11-02 09:00',
                lastUsedAt: '2026-11-10 08:05',
                revokedAt: null,
            }),
        ).toBe('Vinculado el 2 nov · 09:00 · usado el 10 nov · 08:05');
        expect(
            deviceUse({
                enrolledAt: '2026-11-02 09:00',
                lastUsedAt: null,
                revokedAt: null,
            }),
        ).toBe('Vinculado el 2 nov · 09:00 · aún sin marcar');
        expect(
            deviceUse({
                enrolledAt: '2026-10-01 09:00',
                lastUsedAt: '2026-10-10 08:00',
                revokedAt: '2026-11-01 09:00',
            }),
        ).toBe('Vinculado el 1 oct · 09:00 · revocado el 1 nov · 09:00');
    });
});

describe('where a person stands', () => {
    it('shows each state in a chip', () => {
        expect(deviceStatus('linked')).toEqual({
            label: 'Con dispositivo',
            variant: 'success',
        });
        expect(deviceStatus('unlinked')).toEqual({
            label: 'Sin dispositivo',
            variant: 'warning',
        });
        expect(deviceStatus('revoked')).toEqual({
            label: 'Revocado',
            variant: 'secondary',
        });
    });
});
