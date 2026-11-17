import type { UIMessage } from 'ai';
import { describe, expect, it } from 'vite-plus/test';
import { answerLinksOf } from '@/lib/assistant/answer-links';

function answer(outputs: readonly unknown[]): UIMessage {
    return {
        id: 'm-1',
        role: 'assistant',
        parts: [
            ...outputs.map((output, index) => ({
                type: 'tool-GetCampaignProgress' as const,
                toolCallId: `t-${index}`,
                state: 'output-available' as const,
                input: {},
                output,
            })),
            { type: 'text', text: 'Van 142 de 186.' },
        ],
    };
}

describe('answerLinksOf', () => {
    it('has no next step and no sources when the tools bring none, as today', () => {
        expect(
            answerLinksOf(
                answer([JSON.stringify({ title: 'Avance', rows: [] })]),
            ),
        ).toEqual({ next: null, sources: [] });
        expect(answerLinksOf(answer([]))).toEqual({ next: null, sources: [] });
    });

    it('takes the first next step a tool brings and its sources, each once and in order', () => {
        expect(
            answerLinksOf(
                answer([
                    JSON.stringify({
                        next: {
                            label: 'Ver la campaña',
                            link: '/admin/acme/campaigns/1',
                        },
                        sources: ['Campaña 2026', 'Guías I y III'],
                    }),
                    {
                        next: {
                            label: 'Ver otra',
                            link: '/admin/acme/campaigns/2',
                        },
                        sources: ['Campaña 2026', 'Mínimo de 5 por grupo', 7],
                    },
                ]),
            ),
        ).toEqual({
            next: { label: 'Ver la campaña', link: '/admin/acme/campaigns/1' },
            sources: ['Campaña 2026', 'Guías I y III', 'Mínimo de 5 por grupo'],
        });
    });

    it('never takes a next step that leaves the panel or comes half', () => {
        for (const next of [
            { label: 'Fuera', link: 'https://example.com' },
            { label: 'Fuera', link: '//example.com/x' },
            { label: '', link: '/admin/acme' },
            { label: 'Sin enlace' },
            'texto',
        ]) {
            expect(answerLinksOf(answer([{ next }])).next).toBeNull();
        }
    });

    it('ignores what a tool did not finish and output that is not JSON', () => {
        const running: UIMessage = {
            id: 'm-2',
            role: 'assistant',
            parts: [
                {
                    type: 'tool-GetCampaignProgress',
                    toolCallId: 't-1',
                    state: 'input-available',
                    input: {},
                },
            ],
        };

        expect(answerLinksOf(running)).toEqual({ next: null, sources: [] });
        expect(answerLinksOf(answer(['{no es json']))).toEqual({
            next: null,
            sources: [],
        });
    });
});
