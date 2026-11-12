import { describe, expect, it } from 'vite-plus/test';

import {
    closedText,
    mapColumns,
    participationText,
    resultsReading,
    subtractionText,
    tierOf,
    topicOrder,
} from '@/lib/engagement/results';
import type { EngagementDriver, EngagementPriority } from '@/types';

const driver = (key: string, score: number | null = 60): EngagementDriver => ({
    key,
    label: key,
    descriptive: false,
    partial: false,
    favorable: score,
    score,
    delta: null,
    items: [],
});

const priority = (
    key: string,
    level: 'high' | 'medium',
): EngagementPriority => ({
    driver: key,
    label: `Tema ${key}`,
    level,
    levelLabel: level === 'high' ? 'Alta' : 'Media',
});

describe('resultsReading', () => {
    it('says how many of every ten answers are favorable and where to act first', () => {
        expect(resultsReading(62, [priority('recognition', 'high')])).toBe(
            '6 de cada 10 respuestas sobre compromiso son favorables. Donde más puedes mover la aguja es Tema recognition.',
        );
    });

    it('leaves out where to act without priorities, and says nothing without an index', () => {
        expect(resultsReading(71, [])).toBe(
            '7 de cada 10 respuestas sobre compromiso son favorables.',
        );
        expect(resultsReading(null, [])).toBeNull();
    });
});

describe('topicOrder', () => {
    it('puts the prioritized themes first, in their order, and folds the rest', () => {
        const order = topicOrder(
            [driver('a'), driver('b'), driver('c'), driver('d')],
            [priority('c', 'high'), priority('a', 'medium')],
        );

        expect(order.main.map((one) => one.driver.key)).toEqual(['c', 'a']);
        expect(order.main.map((one) => one.priority?.levelLabel)).toEqual([
            'Alta',
            'Media',
        ]);
        expect(order.rest.map((one) => one.driver.key)).toEqual(['b', 'd']);
    });

    it('shows every theme when there are no priorities', () => {
        const order = topicOrder([driver('a'), driver('b')], []);

        expect(order.main.map((one) => one.driver.key)).toEqual(['a', 'b']);
        expect(order.rest).toEqual([]);
    });

    it('ignores a priority of a theme the survey did not ask', () => {
        expect(
            topicOrder([driver('a')], [priority('z', 'high')]).main.map(
                (one) => one.driver.key,
            ),
        ).toEqual(['a']);
    });
});

describe('mapColumns', () => {
    it('takes the first five themes in the order of action', () => {
        expect(
            mapColumns(
                ['a', 'b', 'c', 'd', 'e', 'f'].map((key) => driver(key)),
                [priority('f', 'high')],
            ).map((one) => one.key),
        ).toEqual(['f', 'a', 'b', 'c', 'd']);
    });
});

describe('tierOf', () => {
    it('puts a figure in one of three tiers of one hue, and none when hidden', () => {
        expect(tierOf(49)).toBe('low');
        expect(tierOf(50)).toBe('mid');
        expect(tierOf(69)).toBe('mid');
        expect(tierOf(70)).toBe('high');
        expect(tierOf(null)).toBeNull();
    });
});

describe('subtractionText', () => {
    it('shows how the index is calculated when the rounded shares add up', () => {
        expect(
            subtractionText(
                { detractors: 46, passives: 35, promoters: 19 },
                -27,
            ),
        ).toBe('Se calcula restando: 19 − 46 = −27.');
    });

    it('says nothing when rounding would make the subtraction not match', () => {
        expect(
            subtractionText(
                { detractors: 36, passives: 40, promoters: 24 },
                -13,
            ),
        ).toBeNull();
        expect(subtractionText(null, null)).toBeNull();
    });
});

describe('closedText', () => {
    it('says the day it closed, and nothing while open', () => {
        expect(closedText('2026-06-22')).toBe('Cerró el 22 de junio de 2026');
        expect(closedText(null)).toBeNull();
    });
});

describe('participationText', () => {
    it('says how many answered of how many and the share', () => {
        expect(
            participationText({ invited: 212, responses: 168, percent: 79 }),
        ).toBe('168 de 212 contestaron · 79 %');
    });
});
