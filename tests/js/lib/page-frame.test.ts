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
        'error',
        'complaints-public/form',
        'complaints-public/follow-up',
    ])('leaves %s outside the panel', (name) => {
        expect(isStandalone(name)).toBe(true);
    });

    it.each([
        'dashboard',
        'complaints/channel',
        'campaigns/show',
        'seating/index',
        'complaints-publicity',
    ])('keeps %s inside the panel', (name) => {
        expect(isStandalone(name)).toBe(false);
    });
});
