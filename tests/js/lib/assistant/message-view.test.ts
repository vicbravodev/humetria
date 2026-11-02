import type { UIMessage } from 'ai';
import { describe, expect, it } from 'vite-plus/test';
import { viewOf } from '@/lib/assistant/message-view';

const CARD = JSON.stringify({
    render: 'status_list',
    title: 'Estado',
    link: null,
    note: null,
    truncated: false,
    rows: [],
});

function answer(parts: UIMessage['parts']): UIMessage {
    return { id: 'm-1', role: 'assistant', parts };
}

describe('viewOf', () => {
    it('keeps the order of the answer: its steps on top, then its text and cards as they came', () => {
        const view = viewOf(
            answer([
                { type: 'step-start' },
                {
                    type: 'tool-GetComplianceOverview',
                    toolCallId: 't-1',
                    state: 'output-available',
                    input: {},
                    output: CARD,
                },
                { type: 'text', text: 'Te falta una norma.' },
                {
                    type: 'tool-ListPendingActions',
                    toolCallId: 't-2',
                    state: 'input-available',
                    input: {},
                },
            ]),
        );

        expect(view.steps).toEqual([
            { id: 't-1', tool: 'GetComplianceOverview', done: true },
            { id: 't-2', tool: 'ListPendingActions', done: false },
        ]);
        expect(view.items.map((item) => item.kind)).toEqual(['card', 'text']);
        expect(view.text).toBe('Te falta una norma.');
        expect(view.renders).toEqual(['status_list']);
    });

    it('says a tool is done only when it has its output, and leaves a card out when the output is not one', () => {
        const view = viewOf(
            answer([
                {
                    type: 'tool-SearchRegulations',
                    toolCallId: 't-1',
                    state: 'output-available',
                    input: {},
                    output: 'no es una tarjeta',
                },
                {
                    type: 'tool-GetRiskResults',
                    toolCallId: 't-2',
                    state: 'output-error',
                    input: {},
                    errorText: 'x',
                },
            ]),
        );

        expect(view.steps).toEqual([
            { id: 't-1', tool: 'SearchRegulations', done: true },
            { id: 't-2', tool: 'GetRiskResults', done: false },
        ]);
        expect(view.items).toEqual([]);
    });

    it('joins the text of a message that came in pieces', () => {
        expect(
            viewOf(
                answer([
                    { type: 'text', text: 'Uno ' },
                    { type: 'text', text: 'dos' },
                ]),
            ).text,
        ).toBe('Uno dos');
    });

    it('has nothing for a message with no parts', () => {
        expect(viewOf(answer([]))).toEqual({
            steps: [],
            items: [],
            text: '',
            renders: [],
        });
    });
});
