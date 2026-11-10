import { describe, expect, it } from 'vitest';
import {
    planAfterProfile,
    planDefaults,
    planOf,
    profileAfterPlan,
    profileChoices,
} from '@/lib/billing-form';
import type { AccountCompanyRow, SubscriptionView } from '@/types';

describe('profileChoices', () => {
    it('offers a consultant the channel rates and a direct account only its own', () => {
        expect(profileChoices('consultant').map(({ value }) => value)).toEqual([
            'direct',
            'channel_compliance',
            'channel_integral',
            'negotiated',
        ]);
        expect(profileChoices('direct').map(({ value }) => value)).toEqual([
            'direct',
            'negotiated',
        ]);
    });
});

describe('planOf', () => {
    it('names the plan a channel rate goes with, and none for the others', () => {
        expect(planOf('channel_compliance')).toBe('cumplimiento');
        expect(planOf('channel_integral')).toBe('integral');
        expect(planOf('direct')).toBeNull();
        expect(planOf('negotiated')).toBeNull();
    });
});

describe('planAfterProfile', () => {
    it('takes the plan of a channel rate and keeps the plan otherwise', () => {
        expect(planAfterProfile('jornada', 'channel_integral')).toBe(
            'integral',
        );
        expect(planAfterProfile('jornada', 'direct')).toBe('jornada');
        expect(planAfterProfile('integral', 'negotiated')).toBe('integral');
    });
});

describe('profileAfterPlan', () => {
    it('drops a channel rate to the direct one and keeps the others', () => {
        expect(profileAfterPlan('channel_compliance')).toBe('direct');
        expect(profileAfterPlan('channel_integral')).toBe('direct');
        expect(profileAfterPlan('negotiated')).toBe('negotiated');
        expect(profileAfterPlan('direct')).toBe('direct');
    });
});

describe('planDefaults', () => {
    const company = (
        subscription: AccountCompanyRow['subscription'],
        people = 0,
        workCenters = 0,
    ): AccountCompanyRow => ({
        company_id: 'c',
        trade_name: 'Acme',
        legal_name: 'Acme S.A.',
        rfc: 'ACM010101AB1',
        slug: 'acme',
        status: 'active',
        status_label: 'Activa',
        work_centers: workCenters,
        people,
        linked_since: '1 dic 2026',
        subscription,
    });

    it('starts a company without a plan from the people and centers it has, beginning today', () => {
        expect(planDefaults(company(null, 38, 2), '2026-12-09')).toEqual({
            plan: 'cumplimiento',
            profile: 'direct',
            status: 'active',
            seats: '38',
            centers: '2',
            startsOn: '2026-12-09',
            price: '',
            validUntil: '',
        });
    });

    it('asks for at least one person and one center', () => {
        const defaults = planDefaults(company(null), '2026-12-09');

        expect(defaults.seats).toBe('1');
        expect(defaults.centers).toBe('1');
    });

    it('starts a company with a plan from that plan', () => {
        const subscription: SubscriptionView = {
            plan: 'integral',
            plan_label: 'Integral',
            profile: 'negotiated',
            profile_label: 'Precio negociado',
            status: 'trial',
            status_label: 'En prueba',
            seats: 120,
            centers: 5,
            starts_on: '2026-11-01',
            renews_on: '1 dic 2026',
            catalog_version: 'v1',
            negotiated_price: '8149.50',
            negotiated_valid_until: '2027-06-30',
            quote: {
                requires_quotation: false,
                explanation: '',
                lines: [],
                subtotal: null,
                subtotal_cents: null,
                vat: null,
                total: null,
            },
        };

        expect(planDefaults(company(subscription, 3, 1), '2026-12-09')).toEqual(
            {
                plan: 'integral',
                profile: 'negotiated',
                status: 'trial',
                seats: '120',
                centers: '5',
                startsOn: '2026-11-01',
                price: '8149.50',
                validUntil: '2027-06-30',
            },
        );
    });
});
