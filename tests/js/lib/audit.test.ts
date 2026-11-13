import { describe, expect, it } from 'vite-plus/test';

import { auditFiltered, auditQuery, eventLabel } from '@/lib/audit';

const NONE = { q: '', family: '', company: '', from: '', to: '' };

describe('eventLabel', () => {
    it('writes a known event as a person reads it', () => {
        expect(eventLabel('tenant.created')).toBe('Empresa dada de alta');
    });

    it('falls back to the raw name of one it does not know', () => {
        expect(eventLabel('x.unknown')).toBe('x.unknown');
    });
});

describe('auditQuery', () => {
    it('carries nothing for the defaults', () => {
        expect(auditQuery(NONE)).toEqual({});
        expect(auditQuery({ ...NONE, q: '   ' })).toEqual({});
    });

    it('carries each filter in use, with the search trimmed', () => {
        expect(
            auditQuery({
                q: ' ana ',
                family: 'tenant',
                company: 'c1',
                from: '2026-11-01',
                to: '2026-11-12',
            }),
        ).toEqual({
            q: 'ana',
            family: 'tenant',
            company: 'c1',
            from: '2026-11-01',
            to: '2026-11-12',
        });
    });
});

describe('auditFiltered', () => {
    it('is true as soon as one filter is set', () => {
        expect(auditFiltered(NONE)).toBe(false);
        expect(auditFiltered({ ...NONE, to: '2026-11-12' })).toBe(true);
        expect(auditFiltered({ ...NONE, company: 'c1' })).toBe(true);
    });
});
