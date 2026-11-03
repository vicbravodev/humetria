import { describe, expect, it } from 'vite-plus/test';
import { minGroupStep, stepLabel } from '@/lib/assistant/tool-steps';

describe('stepLabel', () => {
    it('says what Humi is looking at while it runs and once it is done', () => {
        expect(stepLabel('GetCampaignProgress', false)).toBe(
            'Consultando el avance de campañas',
        );
        expect(stepLabel('GetCampaignProgress', true)).toBe(
            'Consulté el avance de campañas',
        );
        expect(stepLabel('GetRiskResults', true)).toBe(
            'Consulté los resultados de riesgo',
        );
    });

    it('has a plain phrase for a tool it does not know', () => {
        expect(stepLabel('AlgoNuevo', false)).toBe(
            'Consultando la información',
        );
    });
});

describe('minGroupStep', () => {
    it('says the minimum of people per group that was applied', () => {
        expect(minGroupStep(5)).toBe(
            'Apliqué el mínimo de 5 personas por grupo',
        );
    });
});
