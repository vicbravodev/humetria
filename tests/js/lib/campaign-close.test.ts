import { describe, expect, it } from 'vite-plus/test';
import { closeShortfall, closeWarnings } from '@/lib/campaign-close';

describe('closeShortfall', () => {
    const progress = (
        completed: number,
        target: number,
        extra: {
            againstSample?: boolean;
            screeningPending?: number;
            screeningTarget?: number;
        } = {},
    ) => ({
        completed,
        target,
        againstSample: extra.againstSample ?? false,
        screeningPending: extra.screeningPending ?? 0,
        screeningTarget: extra.screeningTarget ?? 0,
    });

    it('is what is left to answer, against everyone or against the sample', () => {
        expect(closeShortfall(progress(10, 20))).toEqual({
            main: 10,
            screening: 0,
            againstSample: false,
        });
        expect(
            closeShortfall(
                progress(21, 52, { againstSample: true, screeningPending: 3 }),
            ),
        ).toEqual({
            main: 31,
            screening: 3,
            againstSample: true,
        });
    });

    it('is never negative, and nothing without progress', () => {
        expect(
            closeShortfall(progress(60, 52, { againstSample: true })).main,
        ).toBe(0);
        expect(closeShortfall(null)).toEqual({
            main: 0,
            screening: 0,
            againstSample: false,
        });
    });
});

describe('closeWarnings', () => {
    const progress = (
        completed: number,
        target: number,
        extra: {
            againstSample?: boolean;
            screeningPending?: number;
            screeningTarget?: number;
        } = {},
    ) => ({
        completed,
        target,
        againstSample: extra.againstSample ?? false,
        screeningPending: extra.screeningPending ?? 0,
        screeningTarget: extra.screeningTarget ?? 0,
    });

    it('says nothing when everyone answered', () => {
        expect(closeWarnings(progress(20, 20))).toEqual([]);
        expect(closeWarnings(null)).toEqual([]);
    });

    it('counts the people left and the Guía I open, each on its own', () => {
        expect(
            closeWarnings(progress(10, 20, { screeningPending: 9 })),
        ).toEqual([
            'Faltan 10 personas por contestar.',
            '9 personas no han contestado la Guía I.',
        ]);
        expect(
            closeWarnings(progress(19, 20, { screeningPending: 1 })),
        ).toEqual([
            'Falta 1 persona por contestar.',
            '1 persona no ha contestado la Guía I.',
        ]);
    });

    it('says how many answered the Guía I of how many, when the evaluation applies it', () => {
        expect(
            closeWarnings(
                progress(20, 20, { screeningPending: 12, screeningTarget: 20 }),
            ),
        ).toEqual([
            '12 personas no han contestado la Guía I (Guía I: 8 de 20).',
        ]);
    });

    it('keeps the minimum of the sample when there is one', () => {
        expect(
            closeWarnings(progress(21, 52, { againstSample: true })),
        ).toEqual([
            'Faltan 31 respuestas para el mínimo de la muestra que pide la norma. Considera extender la fecha o sortear reemplazos antes de cerrar.',
        ]);
    });
});
