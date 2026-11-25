import { describe, expect, it } from 'vite-plus/test';
import { seatsText, trialExtensionCopy } from '@/lib/tenant-work';

describe('seatsText', () => {
    it('writes the seats in use against what the plan covers', () => {
        expect(seatsText({ used: 96, amount: 150 })).toBe('96 de 150');
    });

    it('says there is no plan when there is no limit', () => {
        expect(seatsText(null)).toBe('Sin plan');
    });
});

describe('trialExtensionCopy', () => {
    it('names how many companies the trial extends', () => {
        expect(trialExtensionCopy(1)).toBe('Extender la prueba de 1 empresa');
        expect(trialExtensionCopy(3)).toBe('Extender la prueba de 3 empresas');
    });
});
