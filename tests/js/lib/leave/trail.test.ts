import { describe, expect, it } from 'vite-plus/test';

import { leaveTrail } from '@/lib/leave/trail';

describe('leaveTrail', () => {
    it('opens with the family and Vacaciones, then the page', () => {
        expect(leaveTrail('Resumen')).toEqual([
            { title: 'Ordena tu operación' },
            { title: 'Vacaciones' },
            { title: 'Resumen' },
        ]);
    });

    it('keeps the links of the levels between, and the last one is plain', () => {
        expect(
            leaveTrail(
                { title: 'Saldos', href: '/admin/acme/vacations/balances' },
                'Sra. Sara Gutiérrez',
            ),
        ).toEqual([
            { title: 'Ordena tu operación' },
            { title: 'Vacaciones' },
            { title: 'Saldos', href: '/admin/acme/vacations/balances' },
            { title: 'Sra. Sara Gutiérrez' },
        ]);
    });
});
