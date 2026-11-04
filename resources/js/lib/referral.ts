import type { ScreeningReferral, ReferralStatus } from '@/types';

/** The steps of a person's channeling, in order (pantallas: Canalizacion). */
export const REFERRAL_STEPS = ['Por canalizar', 'Canalizada', 'Atendida'];

/** Which step a person is on: 0 while nothing is recorded, 1 once referred, 2 once attended. */
export function referralStep(referral: ScreeningReferral | null): number {
    if (referral === null) {
        return 0;
    }

    return referral.status === 'attended' ? 2 : 1;
}

/** What HR can do next with a person: record the referral, then the attention, then only correct it. */
export function nextAction(referral: ScreeningReferral | null): {
    label: string;
    status: ReferralStatus;
    primary: boolean;
} {
    if (referral === null) {
        return {
            label: 'Registrar canalización',
            status: 'referred',
            primary: true,
        };
    }

    return referral.status === 'referred'
        ? { label: 'Registrar atención', status: 'attended', primary: false }
        : { label: 'Editar registro', status: 'attended', primary: false };
}
