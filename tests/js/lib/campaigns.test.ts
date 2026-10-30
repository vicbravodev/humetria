import { describe, expect, it } from 'vite-plus/test';
import {
    campaignMeta,
    instrumentsText,
    progressCaption,
    progressCount,
    progressNote,
    quotasText,
    sampleSize,
} from '@/lib/campaigns';
import type { CampaignProgress, CampaignSummary } from '@/types';

const summary = (changes: Partial<CampaignSummary> = {}): CampaignSummary => ({
    id: 'c1',
    workCenter: 'Planta Norte',
    kind: 'nom035_evaluation',
    kindLabel: 'Evaluación NOM-035',
    status: 'active',
    statusLabel: 'En curso',
    instruments: ['Guía I', 'Guía III'],
    closesOn: '14 nov 2026',
    closedOn: null,
    progress: null,
    ...changes,
});

const progress = (
    completed: number,
    target: number,
    againstSample = true,
): CampaignProgress => ({
    completed,
    target,
    againstSample,
});

describe('instrumentsText', () => {
    it('names one guide or joins several', () => {
        expect(instrumentsText(['Guía I'])).toBe('Guía I');
        expect(instrumentsText(['Guía I', 'Guía III'])).toBe('Guías I y III');
        expect(instrumentsText(['Guía I', 'Guía II', 'Guía III'])).toBe(
            'Guías I, II y III',
        );
        expect(instrumentsText([])).toBe('');
    });
});

describe('sampleSize', () => {
    it('sizes the Guía III sample as the norm does, rounding up', () => {
        expect(sampleSize(51)).toBe(46);
        expect(sampleSize(100)).toBe(80);
        expect(sampleSize(500)).toBe(218);
        expect(sampleSize(10000)).toBe(370);
    });

    it('has no sample for 50 people or fewer', () => {
        expect(sampleSize(50)).toBeNull();
        expect(sampleSize(0)).toBeNull();
    });
});

describe('progress', () => {
    it('counts answers against the sample or everyone invited', () => {
        expect(progressCount(progress(142, 186))).toBe('142 de 186');
        expect(progressCaption(progress(142, 186))).toBe(
            'respuestas de la muestra requerida',
        );
        expect(progressCaption(progress(3, 20, false))).toBe(
            'personas contestaron',
        );
    });

    it('says what is left, or that it is complete', () => {
        expect(progressNote(progress(142, 186))).toBe('Faltan 44 respuestas');
        expect(progressNote(progress(185, 186))).toBe('Falta 1 respuesta');
        expect(progressNote(progress(186, 186))).toBe('Muestra completa');
        expect(progressNote(progress(190, 186))).toBe('Muestra completa');
        expect(progressNote(progress(20, 20, false))).toBe(
            'Todas las personas contestaron',
        );
    });
});

describe('campaignMeta', () => {
    it('gives the guides and the deadline, or when it closed', () => {
        expect(campaignMeta(summary())).toBe(
            'Guías I y III · cierra el 14 nov 2026',
        );
        expect(
            campaignMeta(
                summary({ status: 'closed', closedOn: '20 oct 2026' }),
            ),
        ).toBe('Guías I y III · cerró el 20 oct 2026');
        expect(campaignMeta(summary({ status: 'draft', closesOn: null }))).toBe(
            'Guías I y III · borrador',
        );
    });
});

describe('quotasText', () => {
    it('lists how many of each gender the sample takes', () => {
        expect(
            quotasText([
                { gender: 'female', label: 'Mujeres', count: 45 },
                { gender: 'male', label: 'Hombres', count: 34 },
                { gender: 'other', label: 'Otro', count: 1 },
            ]),
        ).toBe('Mujeres 45 · Hombres 34 · Otro 1');
        expect(quotasText([])).toBe('');
    });
});
