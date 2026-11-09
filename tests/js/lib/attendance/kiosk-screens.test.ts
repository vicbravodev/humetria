import { describe, expect, it } from 'vitest';
import { screenBadge, screenSeen } from '@/lib/attendance/kiosk-screens';

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
