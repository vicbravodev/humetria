import { describe, expect, it } from 'vite-plus/test';

import { staffLabel } from '@/lib/staff';

describe('staffLabel', () => {
    it('names the platform role of Humetría staff, the superadmin first', () => {
        expect(staffLabel({ isSuperadmin: true, isStaff: true })).toBe(
            'Superadmin',
        );
        expect(staffLabel({ isSuperadmin: false, isStaff: true })).toBe(
            'Soporte',
        );
    });

    it('names nothing for anyone else', () => {
        expect(staffLabel({ isSuperadmin: false, isStaff: false })).toBeNull();
    });
});
