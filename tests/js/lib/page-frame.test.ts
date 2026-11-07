import { describe, expect, it } from 'vitest';
import { isStandalone } from '@/lib/page-frame';

describe('isStandalone', () => {
    it.each([
        'landing',
        'campaigns/survey',
        'seating/portal',
        'telework/portal',
        'telework/portal-checklist',
        'results/shared-report',
        'attendance/kiosk',
        'attendance/enroll',
        'attendance/punch',
        'compensation/shared-report',
        'error',
        'complaints-public/form',
        'complaints-public/follow-up',
        'disclosure/portal-policy',
        'results/portal-support',
        'disclosure-public/center',
        'telework/applies',
        'tenancy/applies',
        'tenancy/onboarding',
        'disclosure/policy-write',
    ])('leaves %s outside the panel', (name) => {
        expect(isStandalone(name)).toBe(true);
    });

    it.each([
        'dashboard',
        'complaints/channel',
        'campaigns/show',
        'seating/index',
        'complaints-publicity',
        'disclosure/index',
        'disclosure/policy',
        'compensation/report',
    ])('keeps %s inside the panel', (name) => {
        expect(isStandalone(name)).toBe(false);
    });
});
