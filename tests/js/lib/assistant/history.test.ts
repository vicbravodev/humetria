import { describe, expect, it } from 'vite-plus/test';
import { viewOf } from '@/lib/assistant/message-view';
import {
    dayLabel,
    groupByDay,
    historyQuery,
    uiMessagesFrom,
} from '@/lib/assistant/history';

// 18 Nov 2026, noon in Mexico City (UTC-6).
const NOW = new Date('2026-11-18T18:00:00Z');

describe('dayLabel', () => {
    it('says today by the day in Mexico City, not in UTC; yesterday is already this week', () => {
        expect(dayLabel('2026-11-18T06:30:00Z', NOW)).toBe('Hoy');
        expect(dayLabel('2026-11-18T06:00:00Z', NOW)).toBe('Hoy');
        expect(dayLabel('2026-11-18T05:59:00Z', NOW)).toBe('Esta semana');
        expect(dayLabel('2026-11-17T12:00:00Z', NOW)).toBe('Esta semana');
    });

    it('says the last week as «Esta semana» and anything older as «Antes»', () => {
        expect(dayLabel('2026-11-12T12:00:00Z', NOW)).toBe('Esta semana');
        expect(dayLabel('2026-11-11T12:00:00Z', NOW)).toBe('Antes');
        expect(dayLabel('2025-01-01T12:00:00Z', NOW)).toBe('Antes');
    });
});

describe('groupByDay', () => {
    it('keeps the order of the rows and starts a group when the label changes', () => {
        const rows = [
            { id: '1', title: 'a', updated_at: '2026-11-18T10:00:00Z' },
            { id: '2', title: 'b', updated_at: '2026-11-18T09:00:00Z' },
            { id: '3', title: 'c', updated_at: '2026-11-17T12:00:00Z' },
        ];

        expect(groupByDay(rows, NOW)).toEqual([
            { label: 'Hoy', rows: [rows[0], rows[1]] },
            { label: 'Esta semana', rows: [rows[2]] },
        ]);
        expect(groupByDay([], NOW)).toEqual([]);
    });
});

describe('uiMessagesFrom', () => {
    it('brings back the buttons of a turn that ended asking, so a reopened conversation shows the question and not an empty answer', () => {
        const choices = JSON.stringify({
            render: 'choices',
            title: '¿De qué parte?',
            link: null,
            note: null,
            truncated: false,
            rows: [{ label: 'De todo', text: 'Quiero ver todo' }],
        });
        const [answer] = uiMessagesFrom({
            messages: [{ id: 'm-3', role: 'assistant', content: '', choices }],
        });

        expect(answer).toBeDefined();

        const view = viewOf(answer ?? { id: '', role: 'assistant', parts: [] });

        expect(view.renders).toEqual(['choices']);
        expect(view.items.map((item) => item.kind)).toEqual(['card']);
        expect(view.steps).toEqual([]);
    });

    it('turns what the server keeps (latest first) into the messages of the conversation, oldest first', () => {
        const body = {
            messages: [
                { id: 'm-3', role: 'assistant', content: 'Va bien.' },
                { id: 'm-2', role: 'user', content: '¿Cómo voy?' },
            ],
        };

        expect(uiMessagesFrom(body)).toEqual([
            {
                id: 'm-2',
                role: 'user',
                parts: [{ type: 'text', text: '¿Cómo voy?' }],
            },
            {
                id: 'm-3',
                role: 'assistant',
                parts: [{ type: 'text', text: 'Va bien.' }],
            },
        ]);
    });

    it('leaves out what is not a message of the person or of Humi, and is empty for anything else', () => {
        expect(
            uiMessagesFrom({
                messages: [
                    { id: 'x', role: 'system', content: 'no' },
                    { id: 'y', role: 'user' },
                    7,
                ],
            }),
        ).toEqual([]);
        expect(uiMessagesFrom(null)).toEqual([]);
        expect(uiMessagesFrom({ messages: 'no' })).toEqual([]);
    });
});

describe('historyQuery', () => {
    it('carries the search only when there is one', () => {
        expect(historyQuery({ q: 'silla' })).toEqual({ q: 'silla' });
        expect(historyQuery({ q: '' })).toEqual({});
    });
});
