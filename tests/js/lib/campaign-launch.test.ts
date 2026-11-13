import { describe, expect, it } from 'vite-plus/test';
import { launchSummary } from '@/lib/campaign-launch';

describe('launchSummary', () => {
    it('says how many emails go today and when the evaluation closes', () => {
        expect(launchSummary(20, '2026-11-26')).toBe(
            'Se enviarán 20 correos hoy. La evaluación cierra el 26 de noviembre de 2026.',
        );
    });

    it('uses the singular for one email', () => {
        expect(launchSummary(1, '2026-11-26')).toBe(
            'Se enviará 1 correo hoy. La evaluación cierra el 26 de noviembre de 2026.',
        );
    });

    it('leaves the closing out when the day is not a date', () => {
        expect(launchSummary(3, '')).toBe('Se enviarán 3 correos hoy.');
    });
});
