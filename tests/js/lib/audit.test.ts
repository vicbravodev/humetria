import { describe, expect, it } from 'vite-plus/test';

import { auditActor, auditFiltered, auditQuery, eventLabel } from '@/lib/audit';

const NONE = { q: '', kind: '', family: '', company: '', from: '', to: '' };

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
                kind: 'support',
                family: 'tenant',
                company: 'c1',
                from: '2026-11-01',
                to: '2026-11-12',
            }),
        ).toEqual({
            q: 'ana',
            kind: 'support',
            family: 'tenant',
            company: 'c1',
            from: '2026-11-01',
            to: '2026-11-12',
        });
    });
});

describe('auditActor', () => {
    const entry = {
        id: 'e1',
        at: '25 nov 2026, 10:42',
        day: 'Hoy · 25 nov',
        time: '10:42',
        kind: null,
        origin: 'Consola',
        event: 'impersonation.started',
        actor: { name: 'Dueña Acme', email: 'duena@acme.mx' },
        impersonator: null,
        company: null,
        subject: null,
        context: {},
        change: [],
    };

    it('says who acted, the system when nobody did', () => {
        expect(auditActor(entry)).toBe('Dueña Acme (duena@acme.mx)');
        expect(auditActor({ ...entry, actor: null })).toBe('Sistema');
    });

    it('says who worked as whom while impersonating', () => {
        expect(
            auditActor({
                ...entry,
                impersonator: { name: 'Ana López', email: 'ana@humetria.mx' },
            }),
        ).toBe('Ana López como Dueña Acme (duena@acme.mx)');
    });
});

describe('auditFiltered', () => {
    it('is true as soon as one filter is set', () => {
        expect(auditFiltered(NONE)).toBe(false);
        expect(auditFiltered({ ...NONE, to: '2026-11-12' })).toBe(true);
        expect(auditFiltered({ ...NONE, company: 'c1' })).toBe(true);
    });
});
