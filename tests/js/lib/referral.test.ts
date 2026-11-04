import { describe, expect, it } from 'vitest';
import { nextAction, referralStep, REFERRAL_STEPS } from '@/lib/referral';
import type { ScreeningReferral } from '@/types';

const record = (status: ScreeningReferral['status']): ScreeningReferral => ({
    status,
    statusLabel: status === 'referred' ? 'Canalizada' : 'Atendida',
    referredOn: '2026-11-18',
    referredOnLabel: '18 nov 2026',
    institution: 'IMSS',
});

describe('referralStep', () => {
    it('is the first step while nothing is recorded', () => {
        expect(referralStep(null)).toBe(0);
    });

    it('moves to the second step once referred', () => {
        expect(referralStep(record('referred'))).toBe(1);
    });

    it('ends on the third step when attended', () => {
        expect(referralStep(record('attended'))).toBe(2);
    });
});

describe('nextAction', () => {
    it('offers to record the referral while pending, as the main action', () => {
        expect(nextAction(null)).toEqual({
            label: 'Registrar canalización',
            status: 'referred',
            primary: true,
        });
    });

    it('offers to record the attention once referred', () => {
        expect(nextAction(record('referred'))).toEqual({
            label: 'Registrar atención',
            status: 'attended',
            primary: false,
        });
    });

    it('only lets an attended record be edited', () => {
        expect(nextAction(record('attended'))).toEqual({
            label: 'Editar registro',
            status: 'attended',
            primary: false,
        });
    });
});

describe('REFERRAL_STEPS', () => {
    it('names the three steps in order', () => {
        expect(REFERRAL_STEPS).toEqual([
            'Por canalizar',
            'Canalizada',
            'Atendida',
        ]);
    });
});
