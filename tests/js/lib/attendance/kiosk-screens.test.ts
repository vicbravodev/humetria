import { describe, expect, it } from 'vitest';
import {
    revokeScreenText,
    screenBadge,
    screenSeen,
} from '@/lib/attendance/kiosk-screens';

describe('screenBadge', () => {
    it('names where each screen stands', () => {
        expect(screenBadge('issued')).toEqual({
            label: 'Emitida, sin activar',
            variant: 'warning',
        });
        expect(screenBadge('active')).toEqual({
            label: 'Activa',
            variant: 'success',
        });
        expect(screenBadge('revoked')).toEqual({
            label: 'Revocada',
            variant: 'secondary',
        });
    });
});

describe('screenSeen', () => {
    it('says when a screen last showed its code, or what is missing', () => {
        expect(
            screenSeen({
                state: 'active',
                lastSeenAt: '2026-11-11 11:57',
                revokedAt: null,
            }),
        ).toBe('Vista el 11 nov · 11:57');
        expect(
            screenSeen({ state: 'issued', lastSeenAt: null, revokedAt: null }),
        ).toBe('Aún no muestra el código');
        expect(
            screenSeen({
                state: 'revoked',
                lastSeenAt: null,
                revokedAt: '2026-11-09 12:00',
            }),
        ).toBe('Revocada el 9 nov · 12:00');
    });
});

describe('revokeScreenText', () => {
    it('says what stops and what to do to use the tablet again', () => {
        expect(revokeScreenText('issued', 0)).toBe(
            'Dejará de mostrar el código de inmediato. Para volver a usar la tableta, emite otra pantalla.',
        );
    });

    it('warns that nobody can scan on site when it is the only active screen', () => {
        expect(revokeScreenText('active', 1)).toBe(
            'Dejará de mostrar el código de inmediato. Es la única pantalla activa de este centro: nadie podrá marcar con el QR en el sitio hasta que active otra. Para volver a usar la tableta, emite otra pantalla.',
        );
    });

    it('does not warn when another screen is active, or when this one never showed the code', () => {
        expect(revokeScreenText('active', 2)).toBe(
            revokeScreenText('issued', 2),
        );
        expect(revokeScreenText('issued', 1)).not.toContain('única');
    });
});
