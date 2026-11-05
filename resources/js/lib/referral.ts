import type { ReferralCounts, ScreeningReferral } from '@/types';

/** Where a person's channeling stands, as its chip says it (board V4-N035-Canalizacion). */
export function referralChip(referral: ScreeningReferral | null): {
    label: string;
    tone: 'pending' | 'done';
} {
    if (referral === null) {
        return { label: 'Por canalizar', tone: 'pending' };
    }

    return {
        label: referral.status === 'attended' ? 'Atendida' : 'Canalizada',
        tone: 'done',
    };
}

/** How many of who requires an assessment were channeled: «3 de 12 canalizadas». No quiso atenderse counts too. */
export function channeledLine(counts: ReferralCounts): {
    done: number;
    total: number;
    text: string;
} {
    const done = counts.referred + counts.attended;
    const total = done + counts.pending;

    return {
        done,
        total,
        text: `${done} de ${total} ${total === 1 ? 'canalizada' : 'canalizadas'}`,
    };
}
