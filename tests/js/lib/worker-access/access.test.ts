import { describe, expect, it } from 'vite-plus/test';

import {
    accessStatus,
    inviteCaption,
    keyKind,
    keyUse,
    nextForPerson,
    reissueText,
    revokeKeyText,
} from '@/lib/worker-access/access';

describe('a key', () => {
    it('says plainly where a synced key lives, and when a key is of one device only', () => {
        expect(keyKind(true)).toEqual({
            label: 'Llave sincronizada',
            hint: 'Vive en la cuenta de llaves de acceso de la persona (iCloud, Google): puede usarse desde otro equipo de esa cuenta, no solo desde este teléfono.',
        });
        expect(keyKind(false)).toEqual({
            label: 'Solo este dispositivo',
            hint: 'La llave no sale de este equipo: para entrar desde otro, la persona necesita un enlace nuevo.',
        });
    });

    it('tells when it was registered, last used or revoked', () => {
        expect(
            keyUse({
                registeredAt: '2026-11-02 09:00',
                lastUsedAt: '2026-11-10 08:05',
                revokedAt: null,
            }),
        ).toBe('Registrada el 2 nov · 09:00 · usada el 10 nov · 08:05');
        expect(
            keyUse({
                registeredAt: '2026-11-02 09:00',
                lastUsedAt: null,
                revokedAt: null,
            }),
        ).toBe('Registrada el 2 nov · 09:00 · aún sin usar');
        expect(
            keyUse({
                registeredAt: '2026-10-01 09:00',
                lastUsedAt: '2026-10-10 08:00',
                revokedAt: '2026-11-01 09:00',
            }),
        ).toBe('Registrada el 1 oct · 09:00 · revocada el 1 nov · 09:00');
    });
});

describe('where a person stands', () => {
    it('shows each state in a chip', () => {
        expect(accessStatus('with_key')).toEqual({
            label: 'Con llave',
            variant: 'success',
        });
        expect(accessStatus('invited')).toEqual({
            label: 'Invitada',
            variant: 'secondary',
        });
        expect(accessStatus('not_invited')).toEqual({
            label: 'Sin invitar',
            variant: 'warning',
        });
    });
});

const key = {
    id: 'k-1',
    label: 'iPhone',
    synced: false,
    registeredAt: '2026-11-09 11:18',
    lastUsedAt: null,
    revokedAt: null,
};

describe('the road of a person to their key', () => {
    it('has not started for someone who was sent nothing', () => {
        expect(
            inviteCaption({ status: 'not_invited', link: null, keys: [] }),
        ).toBe('Aún no se le invita');
    });

    it('says when an open invitation was sent and how long it lasts', () => {
        expect(
            inviteCaption({
                status: 'invited',
                link: {
                    state: 'sent',
                    sentAt: '2026-11-09 09:40',
                    hoursLeft: 46,
                },
                keys: [],
            }),
        ).toBe('Invitada el 9 nov · 09:40 · vence en 46 h');
    });

    it('says an expired invitation no longer opens', () => {
        expect(
            inviteCaption({
                status: 'not_invited',
                link: {
                    state: 'expired',
                    sentAt: '2026-11-01 09:40',
                    hoursLeft: null,
                },
                keys: [],
            }),
        ).toBe('La invitación del 1 nov · 09:40 ya no abre');
    });

    it('asks for a new invitation when every key was revoked', () => {
        expect(
            inviteCaption({
                status: 'not_invited',
                link: null,
                keys: [{ ...key, revokedAt: '2026-11-10 08:00' }],
            }),
        ).toBe('Sus llaves se revocaron: necesita una invitación nueva');
    });

    it('is done once there is a key in use, from the day the newest one was registered', () => {
        expect(
            inviteCaption({ status: 'with_key', link: null, keys: [key] }),
        ).toBe('Con llave desde el 9 nov · 11:18');
    });
});

describe('what comes next for a person', () => {
    it('is to invite them when they have an email, or to hand the link over when they do not', () => {
        const base = { status: 'not_invited' as const, link: null };

        expect(nextForPerson({ ...base, hasEmail: true })).toBe(
            'Tiene correo registrado: se le puede invitar.',
        );
        expect(nextForPerson({ ...base, hasEmail: false })).toBe(
            'Sin correo no podemos enviarle la invitación: crea su enlace y entrégaselo.',
        );
    });

    it('is to wait for an open invitation, and to send another once it expired', () => {
        const open = {
            state: 'sent' as const,
            sentAt: '2026-11-09 09:40',
            hoursLeft: 3,
        };

        expect(
            nextForPerson({ status: 'invited', hasEmail: true, link: open }),
        ).toBe('Esperando que abra su invitación.');
        expect(
            nextForPerson({
                status: 'not_invited',
                hasEmail: true,
                link: { ...open, state: 'expired', hoursLeft: null },
            }),
        ).toBe('La invitación ya no abre: envía otra.');
    });

    it('is nothing for someone with a key', () => {
        expect(
            nextForPerson({ status: 'with_key', hasEmail: true, link: null }),
        ).toBeNull();
    });
});

describe('revoking a key and reissuing an access', () => {
    it('says what revoking a key stops, what is kept, and that a lost phone is a reissue', () => {
        expect(revokeKeyText('Ana López', 'Pixel 8')).toBe(
            'Con «Pixel 8» Ana López ya no podrá entrar ni marcar. Las marcas que ya hizo se conservan. Si perdió el teléfono, mejor reemite su acceso: también cierra las sesiones que tenga abiertas.',
        );
    });

    it('says that reissuing stops every key and session, and where the new link goes', () => {
        expect(reissueText({ name: 'Ana López', hasEmail: true }, 48)).toBe(
            'Todas las llaves de Ana López dejan de servir y se cierran sus sesiones abiertas. Le llega a su correo un enlace nuevo para crear su llave otra vez; funciona una vez y vence en 48 h.',
        );
        expect(reissueText({ name: 'Bob Díaz', hasEmail: false }, 24)).toBe(
            'Todas las llaves de Bob Díaz dejan de servir y se cierran sus sesiones abiertas. No tiene correo: verás el enlace nuevo una sola vez para entregárselo; funciona una vez y vence en 24 h.',
        );
    });
});
