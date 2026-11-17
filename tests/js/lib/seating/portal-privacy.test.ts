import { describe, expect, it } from 'vite-plus/test';

import { PORTAL_PRIVACY } from '@/lib/seating/portal-privacy';

describe('PORTAL_PRIVACY', () => {
    it('says who sees the identified screens and that the seat report is anonymous, never «Confidencial»', () => {
        expect(PORTAL_PRIVACY.identified.label).toBe('Solo RH y la Comisión');
        expect(PORTAL_PRIVACY.anonymous.label).toBe('Anónimo');
        expect(
            Object.values(PORTAL_PRIVACY).map((privacy) => privacy.label),
        ).not.toContain('Confidencial');
    });
});
