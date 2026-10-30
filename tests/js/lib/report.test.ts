import { describe, expect, it } from 'vitest';
import {
    areaBuckets,
    divergingScale,
    domainDescription,
    focusDomains,
    hiddenQuestionnaires,
    highTone,
    mostFrequentLevel,
    nestDomains,
    rankByHighRisk,
    reportSections,
    sideShares,
    waffleCells,
    waffleColumns,
} from '@/lib/report';
import type { AreaResults, RiskGroup } from '@/types';

function group(
    key: string,
    levels: number[],
    category: string | null = null,
): RiskGroup {
    return { key, label: key, category, levels };
}

describe('rankByHighRisk', () => {
    it('puts first the largest share in Alto or Muy alto, then the most in Muy alto, keeping ties in order', () => {
        const rows = [
            group('a', [5, 0, 0, 5, 0]),
            group('b', [0, 0, 0, 0, 10]),
            group('c', [5, 0, 0, 0, 5]),
            group('d', [5, 0, 0, 0, 5]),
        ];

        expect(rankByHighRisk(rows).map((row) => row.key)).toEqual([
            'b',
            'c',
            'd',
            'a',
        ]);
    });
});

describe('focusDomains', () => {
    it('takes up to three domains with someone in Alto or Muy alto, the worst first', () => {
        const domains = [
            group('calm', [10, 0, 0, 0, 0]),
            group('one', [8, 0, 0, 2, 0]),
            group('two', [5, 0, 0, 0, 5]),
            group('three', [7, 0, 0, 3, 0]),
            group('four', [9, 0, 0, 1, 0]),
        ];

        expect(focusDomains(domains).map((row) => row.key)).toEqual([
            'two',
            'three',
            'one',
        ]);
        expect(focusDomains([group('calm', [10, 0, 0, 0, 0])])).toEqual([]);
    });
});

describe('mostFrequentLevel', () => {
    it('is the level most questionnaires fall in, the higher one on a tie', () => {
        expect(mostFrequentLevel([2, 6, 3, 3, 15])).toBe(4);
        expect(mostFrequentLevel([5, 0, 3, 0, 0])).toBe(0);
        expect(mostFrequentLevel([3, 3, 0, 0, 0])).toBe(1);
    });
});

describe('waffleCells', () => {
    it('gives one square per questionnaire from Nulo up, then an empty one per person who did not answer', () => {
        expect(waffleCells([1, 0, 2, 0, 1], 2)).toEqual([
            0,
            2,
            2,
            4,
            null,
            null,
        ]);
        expect(waffleCells([1, 0, 0, 0, 0], -3)).toEqual([0]);
    });
});

describe('waffleColumns', () => {
    it('lays out ten per row up to fifty squares, twenty above', () => {
        expect(waffleColumns(50)).toBe(10);
        expect(waffleColumns(51)).toBe(20);
    });
});

describe('sideShares', () => {
    it('splits a group in Nulo to Medio and in Alto or Muy alto, as shares of it', () => {
        expect(sideShares([2, 1, 1, 3, 3])).toEqual({ low: 40, high: 60 });
        expect(sideShares([0, 0, 0, 0, 0])).toEqual({ low: 0, high: 0 });
    });
});

describe('divergingScale', () => {
    it('reads 0 to 80 % per side, or up to the next ten when a side goes past it', () => {
        expect(divergingScale([group('a', [2, 1, 1, 3, 3])])).toBe(80);
        expect(divergingScale([group('a', [17, 0, 0, 0, 3])])).toBe(90);
        expect(divergingScale([group('a', [0, 0, 0, 10, 0])])).toBe(100);
        expect(divergingScale([])).toBe(80);
    });
});

describe('nestDomains', () => {
    it('puts each category with its domains, both from the worst off', () => {
        const categories = [
            group('calm', [5, 0, 0, 0, 0]),
            group('tense', [0, 0, 0, 5, 0]),
        ];
        const domains = [
            group('c1', [5, 0, 0, 0, 0], 'calm'),
            group('t1', [4, 0, 0, 1, 0], 'tense'),
            group('t2', [0, 0, 0, 0, 5], 'tense'),
        ];

        expect(
            nestDomains(categories, domains).map(({ category, domains }) => [
                category.key,
                domains.map((domain) => domain.key),
            ]),
        ).toEqual([
            ['tense', ['t2', 't1']],
            ['calm', ['c1']],
        ]);
    });
});

describe('areaBuckets', () => {
    it('groups the final score in Nulo or Bajo, Medio, and Alto or Muy alto', () => {
        expect(areaBuckets([1, 2, 3, 4, 5])).toEqual([3, 3, 9]);
    });
});

describe('hiddenQuestionnaires', () => {
    it('counts the questionnaires of the areas not shown', () => {
        const areas: AreaResults = {
            visible: [
                { id: 'a', name: 'A', answered: 12, final: [] },
                { id: 'b', name: 'B', answered: 8, final: [] },
            ],
            hidden: 3,
        };

        expect(hiddenQuestionnaires(29, areas)).toBe(9);
    });
});

describe('domainDescription', () => {
    it('says what a domain of the norm looks at, or nothing for one it does not know', () => {
        expect(domainDescription('leadership')).toBe(
            'Cómo dirige el jefe inmediato: claridad de funciones, apoyo y trato.',
        );
        expect(domainDescription('unknown')).toBeNull();
    });
});

describe('highTone', () => {
    it('marks a share of 60 % or more as Muy alto and of 45 % or more as Alto', () => {
        expect(highTone(60)).toBe('very_high');
        expect(highTone(59)).toBe('high');
        expect(highTone(45)).toBe('high');
        expect(highTone(44)).toBeNull();
    });
});

describe('reportSections', () => {
    it('links every part of the report, and only the 7.7 incises and the annex when there are no results to show', () => {
        expect(reportSections(true).map((section) => section.id)).toEqual([
            'resumen',
            'focos',
            'dominios',
            'areas',
            'informe',
            'anexo',
        ]);
        expect(reportSections(false)).toEqual([
            { id: 'informe', label: 'Informe 7.7' },
            { id: 'anexo', label: 'Anexo' },
        ]);
    });
});
