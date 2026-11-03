import type { UIMessage } from 'ai';
import { describe, expect, it } from 'vite-plus/test';
import { isWaiting, shownMessages, viewOf } from '@/lib/assistant/message-view';

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

describe('viewOf: a repeated lookup', () => {
    const search = (id: string, rows: object[]) => ({
        type: 'tool-SearchRegulations' as const,
        toolCallId: id,
        state: 'output-available' as const,
        input: {},
        output: JSON.stringify({
            render: 'list',
            title: 'Normativa',
            link: null,
            note: null,
            truncated: false,
            rows,
        }),
    });
    const running = (id: string) => ({
        type: 'tool-SearchRegulations' as const,
        toolCallId: id,
        state: 'input-available' as const,
        input: {},
    });

    it('shows one step per tool, done only when every call of it is', () => {
        expect(
            viewOf(answer([search('t-1', []), search('t-2', [])])).steps,
        ).toEqual([{ id: 't-1', tool: 'SearchRegulations', done: true }]);
        expect(
            viewOf(answer([search('t-1', []), running('t-2')])).steps,
        ).toEqual([{ id: 't-1', tool: 'SearchRegulations', done: false }]);
    });

    it('puts everything Humi looked up in one card, each criterion once, and drops a card with nothing new', () => {
        const c1 = { norm: 'NOM-037', id: 'C-01', title: 'Uno', text: 'a' };
        const c2 = { norm: 'NOM-037', id: 'C-02', title: 'Dos', text: 'b' };
        const view = viewOf(
            answer([
                search('t-1', [c1, c2]),
                search('t-2', [c2, { ...c1, norm: 'NOM-035' }]),
                search('t-3', [c1]),
            ]),
        );
        const refs = view.items.map((item) =>
            item.kind === 'card' && item.card.render === 'list'
                ? item.card.rows.map((row) => row.ref)
                : [],
        );

        expect(refs).toEqual([
            ['NOM-037 · C-01', 'NOM-037 · C-02', 'NOM-035 · C-01'],
        ]);
    });
});

describe('isWaiting', () => {
    const user: UIMessage = {
        id: 'u',
        role: 'user',
        parts: [{ type: 'text', text: 'Hola' }],
    };

    it('waits after the question, and while the answer is still empty', () => {
        expect(isWaiting([user], true)).toBe(true);
        expect(isWaiting([user, answer([])], true)).toBe(true);
        expect(isWaiting([user, answer([{ type: 'step-start' }])], true)).toBe(
            true,
        );
        expect(isWaiting([], true)).toBe(true);
    });

    it('stops waiting with the first word or the first step of the answer', () => {
        expect(
            isWaiting([user, answer([{ type: 'text', text: 'Hola' }])], true),
        ).toBe(false);
        expect(
            isWaiting(
                [
                    user,
                    answer([
                        {
                            type: 'tool-SearchRegulations',
                            toolCallId: 't-1',
                            state: 'input-available',
                            input: {},
                        },
                    ]),
                ],
                true,
            ),
        ).toBe(false);
    });

    it('does not wait when nothing is coming', () => {
        expect(isWaiting([user], false)).toBe(false);
        expect(isWaiting([user, answer([])], false)).toBe(false);
    });
});

describe('shownMessages', () => {
    const user: UIMessage = { id: 'u', role: 'user', parts: [] };

    it('leaves out the empty answer while it is waited for, and nothing otherwise', () => {
        const empty = answer([]);

        expect(shownMessages([user, empty], true)).toEqual([user]);
        expect(shownMessages([user, empty], false)).toEqual([user, empty]);
        expect(shownMessages([user], true)).toEqual([user]);
    });
});
