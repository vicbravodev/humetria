import { describe, expect, it } from 'vite-plus/test';

import {
    type Restriction,
    isRestriction,
    restrictionCopy,
    restrictionEffects,
} from '@/lib/tenant-restriction';
import type { TenantRow } from '@/types';

describe('isRestriction', () => {
    const statuses: [TenantRow['status'], boolean][] = [
        ['not_started', false],
        ['trial', false],
        ['active', false],
        ['trial_ended', false],
        ['expired', false],
        ['read_only', true],
        ['suspended', true],
        ['archived', true],
    ];

    it.each(statuses)('says %s is a restriction: %s', (status, expected) => {
        expect(isRestriction(status)).toBe(expected);
    });
});

describe('restrictionCopy', () => {
    const cases: {
        restriction: Restriction;
        action: string;
        title: string;
        destructive: boolean;
    }[] = [
        {
            restriction: 'read_only',
            action: 'Solo lectura',
            title: 'Poner a Acme en solo lectura',
            destructive: false,
        },
        {
            restriction: 'suspended',
            action: 'Suspender',
            title: 'Suspender a Acme',
            destructive: true,
        },
        {
            restriction: 'archived',
            action: 'Archivar',
            title: 'Archivar a Acme',
            destructive: true,
        },
    ];

    it.each(cases)(
        'words $restriction as the action "$action"',
        ({ restriction, ...copy }) => {
            expect(restrictionCopy(restriction, 'Acme')).toEqual({
                ...copy,
                submit: copy.action,
            });
        },
    );

    it('puts the name as it is, even with characters a replacement would read', () => {
        expect(restrictionCopy('suspended', 'A$&B').title).toBe(
            'Suspender a A$&B',
        );
    });
});

const all: Restriction[] = ['read_only', 'suspended', 'archived'];
const panelClosed: Restriction[] = ['suspended', 'archived'];

describe('restrictionEffects', () => {
    const labels = (effects: { label: string }[]) =>
        effects.map((effect) => effect.label);

    it('keeps reading and exporting open when read only, and says nothing is changed', () => {
        const effects = restrictionEffects('read_only', false);

        expect(labels(effects)).toEqual([
            'Acceso',
            'Portales y envíos',
            'Cobro',
            'Datos',
        ]);
        expect(effects[0]?.text).toContain('consultar y exportar');
    });

    it.each(panelClosed)('closes the panel when %s', (restriction) => {
        expect(restrictionEffects(restriction, false)[0]?.text).toContain(
            'Nadie de la empresa entra al panel',
        );
    });

    it('tells that an archived company leaves the normal list', () => {
        expect(labels(restrictionEffects('archived', false))).toContain(
            'Lista',
        );
        expect(labels(restrictionEffects('suspended', false))).not.toContain(
            'Lista',
        );
    });

    it.each(all)(
        'always says the balance does not change and data is never deleted when %s',
        (restriction) => {
            const effects = restrictionEffects(restriction, false);

            expect(
                effects.find((effect) => effect.label === 'Cobro')?.text,
            ).toContain('El saldo no cambia');
            expect(
                effects.find((effect) => effect.label === 'Datos')?.text,
            ).toContain('Nunca se borran');
        },
    );

    it.each(all)(
        'warns, when %s and the company uses Attendance, that it must record working time another way',
        (restriction) => {
            const effects = restrictionEffects(restriction, true);
            const warning = effects.find(
                (effect) => effect.label === 'Asistencia',
            );

            expect(warning?.text).toContain('nadie podrá marcar');
            expect(warning?.text).toContain('por otro medio');
            expect(warning?.text).toContain('no la exime');
        },
    );

    it('says nothing of Attendance to a company that does not use it', () => {
        expect(labels(restrictionEffects('suspended', false))).not.toContain(
            'Asistencia',
        );
    });

    it('puts the Attendance warning right after the access, where it is read first', () => {
        expect(
            labels(restrictionEffects('suspended', true)).slice(0, 2),
        ).toEqual(['Acceso', 'Asistencia']);
    });
});
