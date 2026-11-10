import { describe, expect, it } from 'vite-plus/test';
import { formatTokens, usageLine, usagePercent } from '@/lib/usage';

const month = {
    period_label: 'Noviembre 2026',
    used: 1200,
    reserved: 0,
    quota: 250000,
    granted: 0,
    remaining: 248800,
};

describe('formatTokens', () => {
    it('writes thousands with a comma', () => {
        expect(formatTokens(250000)).toBe('250,000');
        expect(formatTokens(0)).toBe('0');
    });
});

describe('usagePercent', () => {
    it('is what is used and held over the quota, capped at 100', () => {
        expect(usagePercent(month)).toBe(0);
        expect(usagePercent({ ...month, used: 100000, reserved: 25000 })).toBe(
            50,
        );
        expect(usagePercent({ ...month, used: 300000 })).toBe(100);
        expect(usagePercent({ ...month, quota: 0 })).toBe(100);
    });
});

describe('usageLine', () => {
    it('says how many tokens are left, and how many are held while answering', () => {
        expect(usageLine(month)).toBe('Quedan 248,800 tokens.');
        expect(usageLine({ ...month, reserved: 9000, remaining: 239800 })).toBe(
            'Quedan 239,800 tokens; 9,000 están apartados para respuestas en curso.',
        );
    });

    it('says when none are left', () => {
        expect(usageLine({ ...month, used: 250000, remaining: 0 })).toBe(
            'Sin tokens: Humi no responde hasta el mes siguiente, o hasta agregar más.',
        );
    });
});
