import { describe, expect, it } from 'vite-plus/test';
import { chatFailure } from '@/lib/assistant/chat-errors';

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
