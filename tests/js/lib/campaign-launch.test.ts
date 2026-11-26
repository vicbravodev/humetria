import { describe, expect, it } from 'vite-plus/test';
import { launchSummary } from '@/lib/campaign-launch';

const only = (emails: number, whatsapp = 0, withoutContact = 0) => ({
    emails,
    whatsapp,
    withoutContact,
});

describe('launchSummary', () => {
    it('says how many emails go today and when the evaluation closes', () => {
        expect(launchSummary(only(20), '2026-11-26')).toBe(
            'Se enviarán 20 correos hoy. La evaluación cierra el 26 de noviembre de 2026.',
        );
    });

    it('uses the singular for one email', () => {
        expect(launchSummary(only(1), '2026-11-26')).toBe(
            'Se enviará 1 correo hoy. La evaluación cierra el 26 de noviembre de 2026.',
        );
    });

    it('leaves the closing out when the day is not a date', () => {
        expect(launchSummary(only(3), '')).toBe('Se enviarán 3 correos hoy.');
    });

    it('says WhatsApp, not email, when everyone is reached by WhatsApp', () => {
        expect(launchSummary(only(0, 1), '')).toBe(
            'Se enviará 1 WhatsApp hoy.',
        );
        expect(launchSummary(only(0, 4), '')).toBe(
            'Se enviarán 4 WhatsApp hoy.',
        );
    });

    it('names both channels when some go by email and some by WhatsApp', () => {
        expect(launchSummary(only(1, 3), '')).toBe(
            'Se enviarán 1 correo y 3 WhatsApp hoy.',
        );
        expect(launchSummary(only(5, 1), '')).toBe(
            'Se enviarán 5 correos y 1 WhatsApp hoy.',
        );
    });

    it('says how many people will not get it for lack of a contact, and how to reach them', () => {
        expect(launchSummary(only(16, 0, 1), '2026-11-26')).toBe(
            'Se enviarán 16 correos hoy. 1 persona no lo recibirá por no tener correo ni celular: agrégalo en Empleados. La evaluación cierra el 26 de noviembre de 2026.',
        );
        expect(launchSummary(only(16, 0, 3), '')).toBe(
            'Se enviarán 16 correos hoy. 3 personas no lo recibirán por no tener correo ni celular: agrégalo en Empleados.',
        );
    });
});
