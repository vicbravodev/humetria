import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import {
    changesText,
    markCases,
    policyChanges,
    precisionNote,
} from '@/lib/attendance/policy-preview';
import type { AttendancePolicy } from '@/types';

type Row = {
    policy: AttendancePolicy;
    outcomes: Record<string, 'strong' | 'medium' | 'basic' | 'rejected'>;
};

/** The table PHP checks against MarkPolicyCheck: tests/Unit/Attendance/MarkOutcomesFixtureTest.php. */
const rows: Row[] = JSON.parse(
    readFileSync('tests/Fixtures/attendance/mark-outcomes.json', 'utf8'),
);

const saved: AttendancePolicy = {
    outOfZone: 'block',
    maxAccuracyM: 100,
    requireQrOnsite: true,
    trustedMayUseBrowser: true,
    retentionMonths: 12,
    lateToleranceMinutes: 10,
};

describe('markCases', () => {
    it('gives the five cases the level the server decides, under each of the eight policies', () => {
        expect(rows).toHaveLength(8);

        for (const { policy, outcomes } of rows) {
            const levels = Object.fromEntries(
                markCases(policy).map((item) => [item.id, item.outcome.level]),
            );

            expect(levels, JSON.stringify(policy)).toEqual(outcomes);
        }
    });

    it('says with words what each case does under the default policy', () => {
        expect(markCases(saved)).toEqual([
            {
                id: 'qr_and_inside',
                text: 'En el centro, con QR y ubicación dentro de la zona',
                outcome: {
                    level: 'strong',
                    label: 'Fuerte',
                    note: 'QR y ubicación',
                },
            },
            {
                id: 'inside_no_qr',
                text: 'En el centro, sin escanear el QR',
                outcome: {
                    level: 'rejected',
                    label: 'Se rechaza',
                    note: 'Pide escanear la pantalla',
                },
            },
            {
                id: 'qr_inaccurate',
                text: 'Con QR, pero con ubicación peor que 100 m',
                outcome: {
                    level: 'rejected',
                    label: 'Se rechaza',
                    note: 'No se sabe si está dentro de la zona',
                },
            },
            {
                id: 'qr_outside',
                text: 'Con QR, pero fuera de todas las zonas',
                outcome: {
                    level: 'rejected',
                    label: 'Se rechaza',
                    note: 'No se guarda la marca',
                },
            },
            {
                id: 'trusted_browser',
                text: 'Personal de confianza, desde su navegador',
                outcome: {
                    level: 'basic',
                    label: 'Básica',
                    note: 'Sin ubicación guardada',
                },
            },
        ]);
    });

    it('keeps what is allowed with its flag: stored without a zone, as medium evidence', () => {
        const items = markCases({
            ...saved,
            outOfZone: 'allow_and_flag',
            maxAccuracyM: 60,
            requireQrOnsite: false,
            trustedMayUseBrowser: false,
        });
        const note = (id: string) =>
            items.find((item) => item.id === id)?.outcome;

        expect(note('inside_no_qr')).toEqual({
            level: 'medium',
            label: 'Media',
            note: 'Solo la ubicación',
        });
        expect(note('qr_inaccurate')).toEqual({
            level: 'medium',
            label: 'Media',
            note: 'La ubicación no cuenta: se guarda sin zona, señalada',
        });
        expect(note('qr_outside')).toEqual({
            level: 'medium',
            label: 'Media',
            note: 'Se guarda sin zona, señalada',
        });
        expect(note('trusted_browser')).toEqual({
            level: 'medium',
            label: 'Media',
            note: 'Apagado: marca como el resto, con su ubicación',
        });
        expect(items[2]?.text).toBe('Con QR, pero con ubicación peor que 60 m');
    });

    it('sends the trusted staff to the QR when their browser is off and the QR is asked', () => {
        const items = markCases({ ...saved, trustedMayUseBrowser: false });

        expect(items[4]?.outcome).toEqual({
            level: 'rejected',
            label: 'Se rechaza',
            note: 'Apagado: pide escanear la pantalla como el resto',
        });
    });
});

describe('policyChanges', () => {
    it('counts the settings that differ from the saved ones', () => {
        expect(policyChanges(saved, saved)).toBe(0);
        expect(policyChanges(saved, { ...saved, maxAccuracyM: 60 })).toBe(1);
        expect(
            policyChanges(saved, {
                outOfZone: 'allow_and_flag',
                maxAccuracyM: 60,
                requireQrOnsite: false,
                trustedMayUseBrowser: false,
                retentionMonths: 24,
                lateToleranceMinutes: 10,
            }),
        ).toBe(5);
        expect(
            policyChanges(saved, { ...saved, lateToleranceMinutes: 0 }),
        ).toBe(1);
    });
});

describe('changesText', () => {
    it('says there is nothing to save, one change or several', () => {
        expect(changesText(0)).toBe('Sin cambios por guardar');
        expect(changesText(1)).toBe(
            '1 cambio sin guardar · quedará en la bitácora',
        );
        expect(changesText(3)).toBe(
            '3 cambios sin guardar · quedarán en la bitácora',
        );
    });
});

describe('precisionNote', () => {
    it('warns that a loose reading may fall inside a zone without the person being there', () => {
        expect(precisionNote(400)).toContain(
            'Con 400 m, una ubicación con señal débil puede caer dentro de una zona',
        );
    });

    it('says that a strict one only counts the clear readings', () => {
        expect(precisionNote(50)).toBe(
            'Con 50 m, solo cuentan las ubicaciones claras. Quien marque con mala señal verá un aviso y podrá pedir una corrección.',
        );
    });

    it('treats 100 m, the default, as strict', () => {
        expect(precisionNote(100)).toContain('solo cuentan');
        expect(precisionNote(101)).toContain('puede caer dentro');
    });
});
