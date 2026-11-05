import { describe, expect, it } from 'vite-plus/test';
import { channeledLine, referralChip } from '@/lib/referral';
import type { ScreeningReferral } from '@/types';

const record = (status: ScreeningReferral['status']): ScreeningReferral => ({
    status,
    statusLabel: status === 'referred' ? 'Canalizada' : 'Atendida',
    destination: 'imss',
    destinationLabel: 'IMSS',
    told: 'in_person',
    toldLabel: 'En persona, en privado',
    referredOn: '2026-11-18',
    referredOnLabel: '18 nov 2026',
    note: null,
    remindOn: null,
    institution: null,
});

describe('referralChip', () => {
    it('says who waits, who was channeled and who was attended', () => {
        expect(referralChip(null)).toEqual({
            label: 'Por canalizar',
            tone: 'pending',
        });
        expect(referralChip(record('referred'))).toEqual({
            label: 'Canalizada',
            tone: 'done',
        });
        expect(referralChip(record('attended')).label).toBe('Atendida');
    });
});

describe('channeledLine', () => {
    it('counts the channeled and the attended against everyone who requires it', () => {
        expect(channeledLine({ pending: 9, referred: 2, attended: 1 })).toEqual(
            { done: 3, total: 12, text: '3 de 12 canalizadas' },
        );
        expect(
            channeledLine({ pending: 1, referred: 0, attended: 0 }).text,
        ).toBe('0 de 1 canalizada');
    });
});
