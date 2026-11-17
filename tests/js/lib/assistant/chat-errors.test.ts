import { describe, expect, it } from 'vite-plus/test';
import {
    chatFailure,
    rateWaitText,
    reportableFailure,
} from '@/lib/assistant/chat-errors';

describe('chatFailure', () => {
    it('says what the server said when it refused with a reason', () => {
        const body = JSON.stringify({
            reason: 'rate',
            message:
                'Alcanzaste el límite de mensajes por ahora. Puedes seguir en unos minutos.',
            retry_after: 40,
        });

        expect(chatFailure(new Error(body))).toEqual({
            reason: 'rate',
            message:
                'Alcanzaste el límite de mensajes por ahora. Puedes seguir en unos minutos.',
            retryAfter: 40,
        });
    });

    it('takes the reason it does not know as a plain failure with the server message', () => {
        const body = JSON.stringify({
            reason: 'disabled',
            message: 'Humi no está disponible para tu empresa.',
            retry_after: null,
        });

        expect(chatFailure(new Error(body))).toEqual({
            reason: 'disabled',
            message: 'Humi no está disponible para tu empresa.',
            retryAfter: null,
        });
    });

    it('does not show a stack, a page or a status to the person: anything else is a plain, calm message', () => {
        const generic = {
            reason: 'error',
            message: 'No pude responder esta vez. Puedes reintentar.',
            retryAfter: null,
        };

        expect(chatFailure(new Error('<html>Server Error</html>'))).toEqual(
            generic,
        );
        expect(chatFailure(new Error('{"reason": 7}'))).toEqual(generic);
        expect(chatFailure(new Error('{"reason":"rate"}'))).toEqual(generic);
        expect(chatFailure(new Error('null'))).toEqual(generic);
        expect(chatFailure(new Error(''))).toEqual(generic);
    });
});

describe('reportableFailure', () => {
    it('reports a failed answer as an error of its own, without what the response said', () => {
        const reported = reportableFailure(
            new Error(
                '<html>Server Error: ana@x.mx asked about her salary</html>',
            ),
        );

        expect(reported).toBeInstanceOf(Error);
        expect(reported?.name).toBe('HumiStreamError');
        expect(reported?.message).toBe('Humi did not answer');
    });

    it('reports nothing when the server refused on purpose: Humi off, budget used, too many messages', () => {
        expect(
            reportableFailure(
                new Error(
                    '{"reason":"budget","message":"Se agotó el presupuesto."}',
                ),
            ),
        ).toBeNull();
    });
});

describe('rateWaitText', () => {
    it('says in about how many minutes Humi is back, and that the page keeps working', () => {
        expect(rateWaitText(240)).toBe(
            'Hiciste muchas preguntas seguidas. Humi vuelve en unos 4 minutos. La página sigue funcionando.',
        );
        expect(rateWaitText(61)).toBe(
            'Hiciste muchas preguntas seguidas. Humi vuelve en unos 2 minutos. La página sigue funcionando.',
        );
    });

    it('says a minute when it is a minute or less, and some minutes when the server did not say', () => {
        expect(rateWaitText(60)).toBe(
            'Hiciste muchas preguntas seguidas. Humi vuelve en un minuto. La página sigue funcionando.',
        );
        expect(rateWaitText(5)).toBe(
            'Hiciste muchas preguntas seguidas. Humi vuelve en un minuto. La página sigue funcionando.',
        );
        expect(rateWaitText(null)).toBe(
            'Hiciste muchas preguntas seguidas. Humi vuelve en unos minutos. La página sigue funcionando.',
        );
    });
});
