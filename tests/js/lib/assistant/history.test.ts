import { describe, expect, it } from 'vite-plus/test';
import { dayLabel, groupByDay, uiMessagesFrom } from '@/lib/assistant/history';

// 18 Nov 2026, noon in Mexico City (UTC-6).
const NOW = new Date('2026-11-18T18:00:00Z');

describe('dayLabel', () => {
    it('says today and yesterday by the day in Mexico City, not in UTC', () => {
        expect(dayLabel('2026-11-18T06:30:00Z', NOW)).toBe('Hoy');
        expect(dayLabel('2026-11-18T06:00:00Z', NOW)).toBe('Hoy');
        expect(dayLabel('2026-11-18T05:59:00Z', NOW)).toBe('Ayer');
        expect(dayLabel('2026-11-17T12:00:00Z', NOW)).toBe('Ayer');
    });

    it('says the last week as «Esta semana» and anything older as «Anteriores»', () => {
        expect(dayLabel('2026-11-14T12:00:00Z', NOW)).toBe('Esta semana');
        expect(dayLabel('2026-11-11T12:00:00Z', NOW)).toBe('Anteriores');
        expect(dayLabel('2025-01-01T12:00:00Z', NOW)).toBe('Anteriores');
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
            { label: 'Ayer', rows: [rows[2]] },
        ]);
        expect(groupByDay([], NOW)).toEqual([]);
    });
});

describe('uiMessagesFrom', () => {
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
