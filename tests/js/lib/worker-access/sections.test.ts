import { describe, expect, it } from 'vite-plus/test';

import { sectionIcon } from '@/lib/worker-access/sections';

describe('sectionIcon', () => {
    it('gives each tool its own icon and an unknown tool the generic one', () => {
        expect(sectionIcon('vacations')).not.toBe(sectionIcon('seating'));
        expect(sectionIcon('telework')).toBe(sectionIcon('something-new'));
    });

    it('gives marking its own icon', () => {
        expect(sectionIcon('attendance')).not.toBe(sectionIcon('telework'));
    });
});
