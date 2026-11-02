import type { UIMessage } from 'ai';
import { describe, expect, it } from 'vite-plus/test';
import { chatBody, lastUserText } from '@/lib/assistant/chat-request';

function message(id: string, role: UIMessage['role'], text: string): UIMessage {
    return { id, role, parts: [{ type: 'text', text }] };
}

describe('lastUserText', () => {
    it('is the text of the last message of the person, whatever came after it', () => {
        const messages = [
            message('1', 'user', 'Primero'),
            message('2', 'assistant', 'Respuesta'),
            message('3', 'user', 'Segundo'),
            message('4', 'assistant', 'Otra'),
        ];

        expect(lastUserText(messages)).toBe('Segundo');
    });

    it('joins the text parts of the message and leaves out the others', () => {
        const mixed: UIMessage = {
            id: '1',
            role: 'user',
            parts: [
                { type: 'text', text: 'Hola ' },
                { type: 'step-start' },
                { type: 'text', text: 'Humi' },
            ],
        };

        expect(lastUserText([mixed])).toBe('Hola Humi');
    });

    it('is empty when the person has written nothing', () => {
        expect(lastUserText([])).toBe('');
        expect(lastUserText([message('1', 'assistant', 'Hola')])).toBe('');
    });
});

describe('chatBody', () => {
    it('sends only the last message, never the history', () => {
        expect(chatBody('Hola', null, null)).toEqual({ message: 'Hola' });
    });

    it('adds the conversation to continue and the page the person has open', () => {
        expect(
            chatBody('¿Cómo va?', 'c-1', { kind: 'campaign', id: 'k-1' }),
        ).toEqual({
            message: '¿Cómo va?',
            conversationId: 'c-1',
            pageContext: { kind: 'campaign', id: 'k-1' },
        });
    });
});
