import { describe, expect, it } from 'vitest';
import { initialPageName, isStandalone } from '@/lib/page-frame';

describe('isStandalone', () => {
    it.each([
        'landing',
        'campaigns/survey',
        'engagement/survey',
        'engagement/portal-feedback',
        'seating/portal',
        'organization/portal-team',
        'leave/portal',
        'leave/portal-team',
        'leave/portal-statement',
        'telework/portal',
        'telework/portal-checklist',
        'results/shared-report',
        'attendance/kiosk',
        'attendance/enroll',
        'attendance/punch',
        'attendance/week',
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
        'organization/org-chart',
        'complaints-publicity',
        'disclosure/index',
        'disclosure/policy',
        'compensation/report',
    ])('keeps %s inside the panel', (name) => {
        expect(isStandalone(name)).toBe(false);
    });
});

describe('initialPageName', () => {
    it('reads the component of the embedded page', () => {
        expect(
            initialPageName('{"component":"campaigns/survey","props":{}}'),
        ).toBe('campaigns/survey');
    });

    it.each([undefined, null, '', '[]', 'null', '{"component":3}', '{}'])(
        'gives an empty name for %j',
        (raw) => {
            expect(initialPageName(raw)).toBe('');
        },
    );
});
