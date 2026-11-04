import { describe, expect, it } from 'vitest';
import { caseQuery } from '@/lib/complaints/cases';
import type { CaseFilters } from '@/types';

const plain: CaseFilters = { q: '', type: null, center: null, status: 'open' };

describe('caseQuery', () => {
    it('keeps a plain list on a plain address', () => {
        expect(caseQuery(plain)).toEqual({});
    });

    it('asks only for what narrows the list', () => {
        expect(
            caseQuery({
                q: '  abc ',
                type: 'workplace_violence',
                center: { value: 'c1', label: 'Planta' },
                status: 'closed',
            }),
        ).toEqual({
            q: 'abc',
            type: 'workplace_violence',
            center: 'c1',
            status: 'closed',
        });
    });

    it('does not send a blank search', () => {
        expect(caseQuery({ ...plain, q: '   ' })).toEqual({});
    });
});
