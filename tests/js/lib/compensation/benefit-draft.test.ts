import { describe, expect, it } from 'vite-plus/test';
import {
    KIND_HINTS,
    parseBenefitPreview,
    reachText,
    SBC_HINTS,
} from '@/lib/compensation/benefit-draft';

describe('parseBenefitPreview', () => {
    it('reads what the server costed', () => {
        expect(
            parseBenefitPreview({
                people: 68,
                yearly_text: '$1,061,616',
                per_person_month_text: '$1,301',
            }),
        ).toEqual({
            people: 68,
            yearly_text: '$1,061,616',
            per_person_month_text: '$1,301',
        });
    });

    it('refuses anything else', () => {
        expect(parseBenefitPreview(null)).toBeNull();
        expect(
            parseBenefitPreview({
                people: '68',
                yearly_text: '$1',
                per_person_month_text: '$1',
            }),
        ).toBeNull();
        expect(parseBenefitPreview({ people: 68 })).toBeNull();
    });
});

describe('reachText', () => {
    it('says who the cost of today reaches', () => {
        expect(reachText(68, 'unionized')).toBe(
            'al año, a 68 sindicalizados, con sus cuotas',
        );
        expect(reachText(1, 'trusted')).toBe(
            'al año, a 1 persona de confianza, con sus cuotas',
        );
        expect(reachText(90, '')).toBe('al año, a 90 personas, con sus cuotas');
    });

    it('has an example for each way of paying and each answer about the IMSS', () => {
        expect(Object.keys(KIND_HINTS)).toEqual([
            'salary_days',
            'salary_percent',
            'fixed_amount',
            'uma_multiple',
        ]);
        expect(Object.keys(SBC_HINTS)).toEqual([
            'always',
            'up_to_cap',
            'never',
        ]);
    });
});
