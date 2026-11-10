import { describe, expect, it } from 'vitest';
import { attentionItems } from '@/lib/attendance/attention';
import type { AttentionInput } from '@/lib/attendance/attention';

const none: AttentionInput = {
    week: '2026-11-02',
    over: true,
    center: '',
    counts: { all: 6, pending: 0, disputed: 0, confirmed: 6, at_risk: 0 },
    pendingCorrections: 0,
    centersWithoutZones: 0,
    findings: [],
};

describe('attentionItems: what asks for RH', () => {
    it('is empty when nothing waits for RH', () => {
        expect(attentionItems('acme', none)).toEqual([]);
    });

    it('puts first what waits for a decision of RH, then what is at risk, then what is missing', () => {
        const items = attentionItems('acme', {
            ...none,
            counts: {
                all: 6,
                pending: 4,
                disputed: 1,
                confirmed: 1,
                at_risk: 4,
            },
            pendingCorrections: 2,
            centersWithoutZones: 1,
            findings: [
                {
                    center: { id: 'c1', name: 'Planta Norte' },
                    zone: 'Nave 1',
                    radius: 80,
                    tolerance: 100,
                },
            ],
        });

        expect(items.map((item) => item.key)).toEqual([
            'corrections',
            'disputed',
            'at_risk',
            'pending',
            'zone-c1',
            'zones',
        ]);
        expect(items.map((item) => item.tone)).toEqual([
            'alert',
            'alert',
            'warn',
            'warn',
            'warn',
            'warn',
        ]);
    });

    it('words each item and sends it to where it is settled', () => {
        const items = attentionItems('acme', {
            ...none,
            week: '2026-11-02',
            counts: {
                all: 6,
                pending: 4,
                disputed: 2,
                confirmed: 0,
                at_risk: 3,
            },
            pendingCorrections: 2,
            centersWithoutZones: 2,
        });

        expect(items[0]).toMatchObject({
            title: '2 correcciones por resolver',
            action: 'Revisar correcciones',
            href: '/admin/acme/attendance/corrections',
        });
        expect(items[1]).toMatchObject({
            title: '2 personas objetaron su semana',
            action: 'Ver objeciones',
            href: '/admin/acme/attendance?status=disputed&week=2026-11-02',
        });
        expect(items[2]).toMatchObject({
            title: '3 personas cerca o por encima de un tope legal',
            href: '/admin/acme/attendance?status=at_risk&week=2026-11-02',
        });
        expect(items[3]).toMatchObject({
            title: '4 personas no han confirmado su semana',
            href: '/admin/acme/attendance?status=pending&week=2026-11-02',
        });
        expect(items[4]).toMatchObject({
            title: '2 centros sin zonas',
            action: 'Agregar zonas',
            href: '#centros',
        });
    });
});

describe('attentionItems: wording', () => {
    it('speaks of one in the singular', () => {
        const items = attentionItems('acme', {
            ...none,
            counts: {
                all: 6,
                pending: 1,
                disputed: 1,
                confirmed: 0,
                at_risk: 1,
            },
            pendingCorrections: 1,
            centersWithoutZones: 1,
        });

        expect(items.map((item) => item.title)).toEqual([
            '1 corrección por resolver',
            '1 persona objetó su semana',
            '1 persona cerca o por encima de un tope legal',
            '1 persona no ha confirmado su semana',
            '1 centro sin zonas',
        ]);
    });
});

describe('attentionItems: edge cases', () => {
    it('does not ask for answers to a week still in course', () => {
        const items = attentionItems('acme', {
            ...none,
            over: false,
            counts: {
                all: 6,
                pending: 6,
                disputed: 0,
                confirmed: 0,
                at_risk: 0,
            },
        });

        expect(items).toEqual([]);
    });

    it('keeps the center the overview is narrowed to in the links', () => {
        const [item] = attentionItems('acme', {
            ...none,
            center: 'c9',
            counts: {
                all: 6,
                pending: 0,
                disputed: 1,
                confirmed: 5,
                at_risk: 0,
            },
        });

        expect(item?.href).toBe(
            '/admin/acme/attendance?status=disputed&week=2026-11-02&center=c9',
        );
    });

    it('explains a zone smaller than the accuracy the policy accepts', () => {
        const [item] = attentionItems('acme', {
            ...none,
            findings: [
                {
                    center: { id: 'c1', name: 'Planta Norte' },
                    zone: 'Nave 1',
                    radius: 80,
                    tolerance: 100,
                },
            ],
        });

        expect(item).toMatchObject({
            title: 'La zona Nave 1 de Planta Norte es menor que la precisión de la política',
            text: 'Su radio es de 80 m y aceptas lecturas de hasta 100 m de imprecisión: una marca con señal débil podría parecer dentro sin estarlo.',
            action: 'Ajustar la zona',
            href: '/admin/acme/attendance/centers/c1',
        });
    });
});
