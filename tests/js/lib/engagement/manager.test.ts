import { describe, expect, it } from 'vite-plus/test';

import { dumbbellRows, managerReading } from '@/lib/engagement/manager';
import type { FeedbackItem } from '@/types';

const item = (
    key: string,
    favorable: number,
    vsCompany: number | null,
): FeedbackItem => ({ key, text: `Texto ${key}`, favorable, vsCompany });

describe('dumbbellRows', () => {
    it('puts the group beside the company and orders by the difference, the biggest first', () => {
        expect(
            dumbbellRows([
                item('a', 50, -16),
                item('b', 85, 22),
                item('c', 69, 1),
            ]),
        ).toEqual([
            { key: 'b', text: 'Texto b', group: 85, company: 63, delta: 22 },
            { key: 'c', text: 'Texto c', group: 69, company: 68, delta: 1 },
            { key: 'a', text: 'Texto a', group: 50, company: 66, delta: -16 },
        ]);
    });

    it('keeps a question without the company at the end, with no point of the company', () => {
        expect(dumbbellRows([item('x', 70, null), item('y', 60, 5)])).toEqual([
            { key: 'y', text: 'Texto y', group: 60, company: 55, delta: 5 },
            {
                key: 'x',
                text: 'Texto x',
                group: 70,
                company: null,
                delta: null,
            },
        ]);
    });
});

describe('managerReading', () => {
    it('says what the group values most and where the opportunity is', () => {
        expect(managerReading([item('a', 50, -16), item('b', 85, 22)])).toBe(
            'Lo que su grupo más reconoce: «Texto b». La oportunidad está en: «Texto a».',
        );
    });

    it('says nothing with fewer than two questions', () => {
        expect(managerReading([item('a', 50, -16)])).toBeNull();
    });
});
