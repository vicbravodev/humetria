import { describe, expect, it } from 'vite-plus/test';

import {
    deviceKind,
    deviceStatus,
    deviceUse,
    linkProgress,
    nextForPerson,
    panelListQuery,
    stageNames,
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

const device = {
    id: 'd-1',
    label: 'iPhone',
    synced: false,
    enrolledAt: '2026-11-09 11:18',
    lastUsedAt: null,
    revokedAt: null,
};

describe('the road of a person to their phone', () => {
    it('has not started for someone who was sent nothing', () => {
        expect(
            linkProgress({ status: 'unlinked', link: null, devices: [] }),
        ).toEqual({
            steps: ['todo', 'todo', 'todo'],
            caption: 'Aún no se le envía el enlace',
        });
    });

    it('waits on the agreement while a link is open, and says when it was sent and how long it lasts', () => {
        expect(
            linkProgress({
                status: 'unlinked',
                link: {
                    state: 'sent',
                    sentAt: '2026-11-09 09:40',
                    hoursLeft: 46,
                },
                devices: [],
            }),
        ).toEqual({
            steps: ['done', 'current', 'todo'],
            caption: 'Enviado el 9 nov · 09:40 · vence en 46 h',
        });
    });

    it('says an expired link needs a new one', () => {
        expect(
            linkProgress({
                status: 'unlinked',
                link: {
                    state: 'expired',
                    sentAt: '2026-11-01 09:40',
                    hoursLeft: null,
                },
                devices: [],
            }),
        ).toEqual({
            steps: ['current', 'todo', 'todo'],
            caption: 'El enlace del 1 nov · 09:40 ya no abre',
        });
    });

    it('asks for a new link when the only phone was revoked', () => {
        expect(
            linkProgress({
                status: 'revoked',
                link: null,
                devices: [{ ...device, revokedAt: '2026-11-10 08:00' }],
            }),
        ).toEqual({
            steps: ['todo', 'todo', 'todo'],
            caption: 'Su teléfono se revocó: necesita un enlace nuevo',
        });
    });

    it('is done once there is an active device, from the day the newest one was linked', () => {
        expect(
            linkProgress({ status: 'linked', link: null, devices: [device] }),
        ).toEqual({
            steps: ['done', 'done', 'done'],
            caption: 'Vinculada el 9 nov · 11:18',
        });
    });
});

describe('what comes next for a person', () => {
    it('is to send the link when they have an email, or to hand it over when they do not', () => {
        const base = { status: 'unlinked' as const, link: null };

        expect(nextForPerson({ ...base, hasEmail: true })).toBe(
            'Tiene correo registrado: se le puede enviar.',
        );
        expect(nextForPerson({ ...base, hasEmail: false })).toBe(
            'Sin correo no podemos enviarle el enlace: crea el suyo y entrégaselo.',
        );
    });

    it('is to wait for an open link, and to send another once it expired', () => {
        const open = {
            state: 'sent' as const,
            sentAt: '2026-11-09 09:40',
            hoursLeft: 3,
        };

        expect(
            nextForPerson({ status: 'unlinked', hasEmail: true, link: open }),
        ).toBe('Esperando que abra el enlace.');
        expect(
            nextForPerson({
                status: 'unlinked',
                hasEmail: true,
                link: { ...open, state: 'expired', hoursLeft: null },
            }),
        ).toBe('El enlace ya no abre: envía uno nuevo.');
    });

    it('is nothing for someone with a phone', () => {
        expect(
            nextForPerson({ status: 'linked', hasEmail: true, link: null }),
        ).toBeNull();
    });
});

describe('the names of a stage', () => {
    it('are first names, joined as a sentence', () => {
        expect(stageNames({ count: 0, names: [] })).toBe('Nadie');
        expect(stageNames({ count: 1, names: ['Bob Díaz'] })).toBe('Bob');
        expect(
            stageNames({ count: 2, names: ['Bob Díaz', 'Carla Ruiz'] }),
        ).toBe('Bob y Carla');
        expect(
            stageNames({
                count: 3,
                names: ['Bob Díaz', 'Carla Ruiz', 'Elena Vega'],
            }),
        ).toBe('Bob, Carla y Elena');
    });

    it('count those beyond the three named', () => {
        expect(
            stageNames({
                count: 5,
                names: ['Bob Díaz', 'Carla Ruiz', 'Elena Vega'],
            }),
        ).toBe('Bob, Carla, Elena y 2 más');
    });
});
