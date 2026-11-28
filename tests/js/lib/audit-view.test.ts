import { describe, expect, it } from 'vite-plus/test';

import { auditDays, auditTag, periodChip } from '@/lib/audit-view';
import type { AuditEntryRow } from '@/types';

const entry = (over: Partial<AuditEntryRow> = {}): AuditEntryRow => ({
    id: 'e1',
    at: '25 nov 2026, 10:42',
    day: 'Hoy · 25 nov',
    time: '10:42',
    kind: null,
    origin: 'Consola',
    event: 'tenant.updated',
    actor: null,
    impersonator: null,
    company: null,
    subject: null,
    context: {},
    change: [],
    ...over,
});

const FAMILIES = [
    { value: 'tenant', label: 'Empresas' },
    { value: 'tenant_member', label: 'Miembros' },
];

describe('auditDays', () => {
    it('groups the entries by their day, in the order they come', () => {
        const days = auditDays([
            entry({ id: 'a' }),
            entry({ id: 'b' }),
            entry({ id: 'c', day: 'Ayer · 24 nov' }),
        ]);

        expect(
            days.map((day) => [day.day, day.entries.map(({ id }) => id)]),
        ).toEqual([
            ['Hoy · 25 nov', ['a', 'b']],
            ['Ayer · 24 nov', ['c']],
        ]);
    });

    it('gives nothing for no entries', () => {
        expect(auditDays([])).toEqual([]);
    });
});

describe('auditTag', () => {
    it('tags an entry by its view of the trail, each in its own tone', () => {
        expect(auditTag(entry({ kind: 'support' }), FAMILIES)).toEqual({
            label: 'Soporte',
            variant: 'warning',
        });
        expect(auditTag(entry({ kind: 'billing' }), FAMILIES)).toEqual({
            label: 'Cobro o plan',
            variant: 'success',
        });
        expect(auditTag(entry({ kind: 'results' }), FAMILIES)).toEqual({
            label: 'Resultados',
            variant: 'destructive',
        });
        expect(auditTag(entry({ kind: 'roles' }), FAMILIES)).toEqual({
            label: 'Roles',
            variant: 'default',
        });
    });

    it('tags any other entry by its family, by the whole part before the dot', () => {
        expect(
            auditTag(entry({ event: 'tenant_member.removed' }), FAMILIES),
        ).toEqual({ label: 'Miembros', variant: 'secondary' });
        expect(auditTag(entry({ event: 'x.unknown' }), FAMILIES)).toEqual({
            label: 'Otro',
            variant: 'secondary',
        });
    });
});

describe('periodChip', () => {
    it('says nothing when the trail is not narrowed by days', () => {
        expect(periodChip('', '')).toBeNull();
    });

    it('says the days of the period as a person reads them', () => {
        expect(periodChip('2026-11-03', '2026-11-09')).toBe(
            '3 nov 2026 – 9 nov 2026',
        );
        expect(periodChip('2026-11-03', '2026-11-03')).toBe('3 nov 2026');
        expect(periodChip('2026-01-15', '')).toBe('Desde 15 ene 2026');
        expect(periodChip('', '2026-12-31')).toBe('Hasta 31 dic 2026');
    });
});
