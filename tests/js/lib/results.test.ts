import { describe, expect, it } from 'vitest';
import {
    hiddenAreasText,
    levelSegments,
    needingAction,
    participationText,
    percentText,
} from '@/lib/results';

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
