import { describe, expect, it } from 'vite-plus/test';

import {
    centerFollowUp,
    centerSize,
    centerStatus,
    guidesReason,
    numeralsText,
    openCampaignLine,
    periodText,
    SIZES,
} from '@/lib/evaluation-centers';
import type { CampaignSummary, WorkCenterEvaluation } from '@/types';

const center: WorkCenterEvaluation = {
    id: 'c1',
    name: 'Planta Querétaro',
    activity: 'Manufactura de autopartes',
    workers: 30,
    guides: ['I', 'II'],
    lastEvaluatedOn: null,
    dueOn: null,
    overdue: false,
    lastCampaignId: null,
    openCampaign: null,
    eligible: true,
};

const campaign: CampaignSummary = {
    id: 'e1',
    workCenter: 'Planta Querétaro',
    kind: 'nom035_evaluation',
    kindLabel: 'Evaluación NOM-035',
    status: 'active',
    statusLabel: 'En curso',
    instruments: ['Guía I', 'Guía II'],
    closesOn: '10 nov 2026',
    closedOn: null,
    launchedOn: '27 oct 2026',
    progress: { completed: 18, target: 60, againstSample: false },
};

describe('numeralsText', () => {
    it('joins guide numerals, or guide titles, as the cards write them', () => {
        expect(numeralsText([])).toBe('—');
        expect(numeralsText(['I'])).toBe('I');
        expect(numeralsText(['I', 'III'])).toBe('I y III');
        expect(numeralsText(['Guía I', 'Guía II'])).toBe('I y II');
        expect(numeralsText(['I', 'II', 'III'])).toBe('I, II y III');
    });
});

describe('centerSize', () => {
    it('places a work center by the guides its size calls for', () => {
        expect(centerSize([])).toBeNull();
        expect(centerSize(['I'])?.key).toBe('small');
        expect(centerSize(['I', 'II'])?.range).toBe('De 16 a 50');
        expect(centerSize(['I', 'III'])?.range).toBe('Más de 50');
        expect(SIZES.map((size) => size.guides)).toEqual([
            'Guía I',
            'Guías I y II',
            'Guías I y III',
        ]);
    });
});

describe('guidesReason', () => {
    it('says why those guides apply', () => {
        expect(guidesReason([])).toBe(
            'Sin personal todavía: ninguna guía aplica.',
        );
        expect(guidesReason(['I'])).toBe(
            'Con hasta 15 personas aplica solo la Guía I.',
        );
        expect(guidesReason(['I', 'II'])).toBe(
            'De 16 a 50 personas aplican las Guías I y II.',
        );
        expect(guidesReason(['I', 'III'])).toBe(
            'Con más de 50 personas aplican las Guías I y III.',
        );
    });
});

describe('centerStatus', () => {
    it('reads a running evaluation first, then the due date, then a draft', () => {
        expect(centerStatus({ ...center, openCampaign: campaign })).toEqual({
            label: 'En curso',
            variant: 'default',
        });
        expect(
            centerStatus({
                ...center,
                lastEvaluatedOn: '29 oct 2024',
                overdue: true,
                openCampaign: { ...campaign, status: 'draft' },
            }),
        ).toEqual({ label: 'Vencida', variant: 'destructive' });
        expect(
            centerStatus({
                ...center,
                lastEvaluatedOn: '29 oct 2026',
                openCampaign: { ...campaign, status: 'draft' },
            }),
        ).toEqual({ label: 'Al día', variant: 'success' });
        expect(
            centerStatus({
                ...center,
                openCampaign: { ...campaign, status: 'draft' },
            }),
        ).toEqual({ label: 'Borrador', variant: 'secondary' });
        expect(centerStatus({ ...center, workers: 0, guides: [] })).toEqual({
            label: 'Sin personal',
            variant: 'secondary',
        });
        expect(centerStatus(center)).toEqual({
            label: 'Sin evaluar',
            variant: 'warning',
        });
    });
});

describe('centerFollowUp', () => {
    it('says when the next evaluation is due and whether a draft waits', () => {
        expect(centerFollowUp(center)).toBe(
            'La norma pide evaluar al menos cada dos años',
        );
        expect(
            centerFollowUp({
                ...center,
                dueOn: '29 oct 2028',
                openCampaign: { ...campaign, status: 'draft' },
            }),
        ).toBe('Siguiente antes del 29 oct 2028 · borrador nuevo sin lanzar');
        expect(
            centerFollowUp({ ...center, dueOn: '20 oct 2026', overdue: true }),
        ).toBe('Venció el 20 oct 2026');
        expect(
            centerFollowUp({
                ...center,
                openCampaign: { ...campaign, status: 'draft' },
            }),
        ).toBe('Borrador sin lanzar');
    });
});

describe('openCampaignLine', () => {
    it('says when it closes and how many answers are missing', () => {
        expect(openCampaignLine(campaign)).toBe(
            'Cierra el 10 nov 2026 · faltan 42',
        );
        expect(
            openCampaignLine({
                ...campaign,
                progress: { completed: 60, target: 60, againstSample: false },
            }),
        ).toBe('Cierra el 10 nov 2026 · completa');
        expect(openCampaignLine({ ...campaign, progress: null })).toBe(
            'Cierra el 10 nov 2026',
        );
    });
});

describe('periodText', () => {
    it('writes from launch to close, without repeating the month or year', () => {
        expect(periodText('10 oct 2026', '29 oct 2026')).toBe(
            '10 al 29 oct 2026',
        );
        expect(periodText('20 sep 2026', '5 oct 2026')).toBe(
            '20 sep al 5 oct 2026',
        );
        expect(periodText('20 dic 2025', '5 ene 2026')).toBe(
            '20 dic 2025 al 5 ene 2026',
        );
        expect(periodText(null, '5 ene 2026')).toBe('Hasta el 5 ene 2026');
        expect(periodText('5 ene 2026', null)).toBe('Desde el 5 ene 2026');
    });
});
