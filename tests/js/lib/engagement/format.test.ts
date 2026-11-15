import { describe, expect, it } from 'vite-plus/test';
import {
    deltaText,
    favorableText,
    pointsText,
    shareWidth,
    versusCompanyText,
} from '@/lib/engagement/format';

describe('favorableText', () => {
    it('writes the share with a non-breaking space before the sign', () => {
        expect(favorableText(72)).toBe('72 %');
        expect(favorableText(0)).toBe('0 %');
    });

    it('says it is under the minimum, never how many are missing, when it is hidden', () => {
        expect(favorableText(null)).toBe(
            'Menos de 5 respuestas: no se muestra',
        );
    });
});

describe('pointsText', () => {
    it('signs the points, with a real minus', () => {
        expect(pointsText(7)).toBe('+7 pts');
        expect(pointsText(-3)).toBe('−3 pts');
        expect(pointsText(0)).toBe('0 pts');
    });

    it('writes one point in the singular', () => {
        expect(pointsText(1)).toBe('+1 pt');
        expect(pointsText(-1)).toBe('−1 pt');
    });
});

describe('deltaText', () => {
    it('names the direction when there is an arrow', () => {
        expect(deltaText({ points: 7, direction: 'up' })).toBe('Sube +7 pts');
        expect(deltaText({ points: -4, direction: 'down' })).toBe(
            'Baja −4 pts',
        );
        expect(deltaText({ points: 0, direction: 'same' })).toBe('Igual');
    });

    it('gives the points alone when the groups are too small for an arrow', () => {
        expect(deltaText({ points: 7, direction: null })).toBe('+7 pts');
    });
});

describe('versusCompanyText', () => {
    it('says how a group stands against the company', () => {
        expect(versusCompanyText(5)).toBe('+5 pts frente a la empresa');
        expect(versusCompanyText(-2)).toBe('−2 pts frente a la empresa');
        expect(versusCompanyText(0)).toBe('Igual que la empresa');
    });

    it('says nothing when either is hidden', () => {
        expect(versusCompanyText(null)).toBe('');
    });
});

describe('shareWidth', () => {
    it('keeps a share between nothing and the whole bar', () => {
        expect(shareWidth(-5)).toBe(0);
        expect(shareWidth(40)).toBe(40);
        expect(shareWidth(120)).toBe(100);
        expect(shareWidth(null)).toBe(0);
    });
});
