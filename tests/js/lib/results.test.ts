import { describe, expect, it } from 'vitest';
import {
    highRisk,
    rankBySeverity,
    rankItems,
    riskVerdict,
    shareOf,
    unfavorableText,
    worstGroupText,
    hiddenAreasText,
    levelSegments,
    needingAction,
    participationText,
    percentText,
    periodText,
    resultsTrail,
} from '@/lib/results';
import { index, show } from '@/routes/tenant/campaigns';

describe('percentText', () => {
    it('rounds a share to a whole percent', () => {
        expect(percentText(1, 3)).toBe('33 %');
        expect(percentText(2, 3)).toBe('67 %');
        expect(percentText(5, 5)).toBe('100 %');
    });

    it('is 0 % of nothing', () => {
        expect(percentText(0, 0)).toBe('0 %');
    });
});

describe('participationText', () => {
    it('says how many answered of how many were invited', () => {
        expect(participationText({ answered: 11, invited: 12 })).toBe(
            '11 de 12 (92 %)',
        );
    });
});

describe('needingAction', () => {
    it('adds the questionnaires from Medio up', () => {
        expect(needingAction([4, 3, 2, 1, 1])).toBe(4);
        expect(needingAction([4, 3, 0, 0, 0])).toBe(0);
    });
});

describe('levelSegments', () => {
    it('fills one segment for Nulo up to five for Muy alto', () => {
        expect(levelSegments('null')).toBe(1);
        expect(levelSegments('medium')).toBe(3);
        expect(levelSegments('very_high')).toBe(5);
    });
});

describe('hiddenAreasText', () => {
    it('says how many areas are not shown', () => {
        expect(hiddenAreasText(0)).toBeNull();
        expect(hiddenAreasText(1)).toBe('1 área no se muestra');
        expect(hiddenAreasText(3)).toBe('3 áreas no se muestran');
    });
});

describe('highRisk', () => {
    it('adds the questionnaires in Alto and Muy alto', () => {
        expect(highRisk([2, 6, 3, 3, 15])).toBe(18);
        expect(highRisk([4, 3, 2, 0, 0])).toBe(0);
    });
});

describe('shareOf', () => {
    it('is the share as a number, unrounded, for widths', () => {
        expect(shareOf(1, 3)).toBeCloseTo(33.333, 2);
        expect(shareOf(0, 0)).toBe(0);
    });
});

describe('rankBySeverity', () => {
    it('puts first the group with the largest share in Alto or Muy alto, then from Medio up, keeping ties in order', () => {
        const rows = [
            { key: 'a', levels: [5, 0, 0, 0, 5] },
            { key: 'b', levels: [0, 0, 0, 10, 10] },
            { key: 'c', levels: [0, 0, 5, 0, 5] },
            { key: 'd', levels: [5, 0, 0, 0, 5] },
        ];

        expect(rankBySeverity(rows).map((row) => row.key)).toEqual([
            'b',
            'c',
            'a',
            'd',
        ]);
    });
});

describe('riskVerdict', () => {
    it('says how many are in Alto or Muy alto', () => {
        expect(riskVerdict([2, 6, 3, 3, 15])).toBe(
            '18 de 29 cuestionarios (62 %) quedan en riesgo alto o muy alto.',
        );
    });

    it('says when none is, but some are in Medio', () => {
        expect(riskVerdict([2, 6, 2, 0, 0])).toBe(
            'Ningún cuestionario queda en riesgo alto o muy alto; 2 de 10 (20 %) quedan en riesgo medio.',
        );
    });

    it('says when every questionnaire is in Nulo or Bajo', () => {
        expect(riskVerdict([4, 6, 0, 0, 0])).toBe(
            'Los 10 cuestionarios quedan en riesgo nulo o bajo: la norma no pide un Programa de intervención.',
        );
    });
});

describe('worstGroupText', () => {
    const worst = {
        key: 'a',
        label: 'Carga de trabajo',
        levels: [0, 0, 2, 3, 5],
    };
    const calm = { key: 'b', label: 'Liderazgo', levels: [5, 5, 0, 0, 0] };
    const groups = [worst, calm];

    it('names the group with the largest share in Alto or Muy alto', () => {
        expect(worstGroupText(groups, 'dominio')).toBe(
            'El dominio que más pesa es Carga de trabajo, con 80 % en riesgo alto o muy alto.',
        );
    });

    it('is nothing when no group has anyone in Alto or Muy alto', () => {
        expect(worstGroupText([calm], 'dominio')).toBeNull();
        expect(worstGroupText([], 'dominio')).toBeNull();
    });
});

describe('unfavorableText', () => {
    it('says how many answered a question unfavorably', () => {
        expect(unfavorableText(7, 29)).toBe('7 de 29 desfavorables (24 %)');
    });
});

describe('rankItems', () => {
    it('puts first the question with the largest share of unfavorable answers, and the hidden ones last', () => {
        const items = [
            { number: 1, text: 'a', answered: 10, counts: [], unfavorable: 2 },
            {
                number: 2,
                text: 'b',
                answered: 10,
                counts: null,
                unfavorable: null,
            },
            { number: 3, text: 'c', answered: 5, counts: [], unfavorable: 4 },
            { number: 4, text: 'd', answered: 10, counts: [], unfavorable: 2 },
        ];

        expect(rankItems(items).map((item) => item.number)).toEqual([
            3, 1, 4, 2,
        ]);
    });
});

describe('periodText', () => {
    it('writes the month and year once when both days share them', () => {
        expect(periodText('10 oct 2026', '29 oct 2026')).toBe(
            '10 al 29 oct 2026',
        );
    });

    it('writes the year once when only the year is shared', () => {
        expect(periodText('28 oct 2026', '3 nov 2026')).toBe(
            '28 oct al 3 nov 2026',
        );
    });

    it('writes both days whole across years', () => {
        expect(periodText('20 dic 2025', '5 ene 2026')).toBe(
            '20 dic 2025 al 5 ene 2026',
        );
    });

    it('says since when while it has not closed', () => {
        expect(periodText('27 oct 2026', null)).toBe('Desde el 27 oct 2026');
    });

    it('is nothing before it starts', () => {
        expect(periodText(null, null)).toBeNull();
    });
});

describe('resultsTrail', () => {
    it('goes from NOM-035 through the evaluation to the page', () => {
        expect(
            resultsTrail(
                'acme',
                { id: 'c1', workCenter: 'Planta Norte' },
                'Resultados',
            ),
        ).toEqual([
            { title: 'NOM-035', href: index('acme') },
            { title: 'Planta Norte', href: show(['acme', 'c1']) },
            { title: 'Resultados' },
        ]);
    });
});
