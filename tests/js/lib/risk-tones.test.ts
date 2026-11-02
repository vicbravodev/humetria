import { describe, expect, it } from 'vite-plus/test';
import { RISK_LEVELS } from '@/lib/results';
import { riskFill, riskFillAt, riskLabel, riskText } from '@/lib/risk-tones';

describe('risk tones', () => {
    it('paints each level with its own token, from Nulo to Muy alto', () => {
        expect(RISK_LEVELS.map(riskFill)).toEqual([
            'bg-risk-nulo',
            'bg-risk-bajo',
            'bg-risk-medio',
            'bg-risk-alto',
            'bg-risk-muy-alto',
        ]);
    });

    it('labels a level on its soft fill, in its readable shade', () => {
        expect(riskLabel('medium')).toBe('bg-risk-medio-bg text-risk-medio-fg');
        expect(riskText('very_high')).toBe('text-risk-muy-alto-fg');
    });

    it('finds the fill by the place of a level, as the waffle counts them, and none past Muy alto', () => {
        expect(riskFillAt(0)).toBe('bg-risk-nulo');
        expect(riskFillAt(4)).toBe('bg-risk-muy-alto');
        expect(riskFillAt(5)).toBe('');
    });
});
