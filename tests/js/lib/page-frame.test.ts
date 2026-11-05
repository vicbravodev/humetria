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
        'compensation/shared-report',
        'error',
        'complaints-public/form',
        'complaints-public/follow-up',
        'disclosure/portal-policy',
        'disclosure-public/center',
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
