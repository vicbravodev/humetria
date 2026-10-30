import { describe, expect, it } from 'vite-plus/test';

import {
    employeeQuery,
    formatMobile,
    headcountText,
    importButtonText,
    importFileHint,
    importStep,
    importStepState,
    importStats,
    importSummaryText,
    withoutEmailText,
} from '@/lib/employees';

const none = {
    q: '',
    work_center: null,
    area: null,
    status: 'active' as const,
    without_email: false,
};

describe('employeeQuery', () => {
    it('leaves out what does not narrow the list', () => {
        expect(employeeQuery(none)).toEqual({});
        expect(employeeQuery({ ...none, q: '   ' })).toEqual({});
    });

    it('keeps the text, the center, the area and the inactive status', () => {
        expect(
            employeeQuery({
                q: ' ana ',
                work_center: { value: 'c1', label: 'Planta Norte' },
                area: { value: 'a1', label: 'Ventas' },
                status: 'inactive',
                without_email: true,
            }),
        ).toEqual({
            q: 'ana',
            work_center: 'c1',
            area: 'a1',
            status: 'inactive',
            without_email: '1',
        });
    });
});

describe('headcountText', () => {
    it('says how many people are active and in how many work centers', () => {
        expect(headcountText({ active: 90, work_centers: 2 })).toBe(
            '90 personas activas en 2 centros de trabajo.',
        );
        expect(headcountText({ active: 1, work_centers: 1 })).toBe(
            '1 persona activa en 1 centro de trabajo.',
        );
    });

    it('says nobody is active without naming centers', () => {
        expect(headcountText({ active: 0, work_centers: 0 })).toBe(
            'Nadie activo por ahora.',
        );
    });
});

describe('withoutEmailText', () => {
    it('names who has no email and how to see them', () => {
        expect(withoutEmailText(3)).toEqual({
            fact: '3 personas no tienen correo',
            action: 'Ver a las 3',
        });
        expect(withoutEmailText(1)).toEqual({
            fact: '1 persona no tiene correo',
            action: 'Ver quién es',
        });
    });
});

describe('importStep', () => {
    it('starts at uploading, reviews a file with problems and confirms a ready one', () => {
        expect(importStep(undefined)).toBe(1);
        expect(importStep({ status: 'invalid' })).toBe(2);
        expect(importStep({ status: 'ready' })).toBe(3);
    });
});

describe('formatMobile', () => {
    it('groups a Mexican mobile as people write it', () => {
        expect(formatMobile('5512345678')).toBe('55 1234 5678');
    });

    it('leaves anything else as it is', () => {
        expect(formatMobile('123')).toBe('123');
    });
});

describe('importSummaryText', () => {
    it('says what a ready file will do, in Spanish with its plurals', () => {
        expect(importSummaryText({ rows: 3, created: 1, updated: 2 })).toBe(
            'Listo para importar 3 empleados: 1 nuevo y 2 actualizados.',
        );
        expect(importSummaryText({ rows: 1, created: 0, updated: 1 })).toBe(
            'Listo para importar 1 empleado: 0 nuevos y 1 actualizado.',
        );
    });

    it('names the import button after the rows', () => {
        expect(importButtonText(1)).toBe('Importar 1 empleado');
        expect(importButtonText(20)).toBe('Importar 20 empleados');
    });
});

describe('importStepState', () => {
    it('marks the steps before the current one as done and the rest as next', () => {
        expect([1, 2, 3].map((n) => importStepState(n, 2))).toEqual([
            'done',
            'current',
            'next',
        ]);
    });
});

describe('importFileHint', () => {
    it('says what the box holds: nothing yet, a file to check or its rows', () => {
        expect(importFileHint(null, undefined)).toBe(
            'Con las columnas de la plantilla, una fila por persona.',
        );
        expect(importFileHint('a.csv', undefined)).toBe('Listo para revisar');
        expect(importFileHint('a.csv', 1)).toBe('1 fila');
        expect(importFileHint('a.csv', 1200)).toBe('1,200 filas');
    });
});

describe('importStats', () => {
    it('counts what a ready file adds and updates, with nothing to fix', () => {
        expect(
            importStats({
                status: 'ready',
                rows: 3,
                created: 2,
                updated: 1,
                errors: [],
            }).map((s) => [s.label, s.value]),
        ).toEqual([
            ['Personas nuevas', 2],
            ['Se actualizan', 1],
            ['Por corregir', 0],
        ]);
    });

    it('counts the rows to fix of a file with problems, as an alert', () => {
        const stats = importStats({
            status: 'invalid',
            rows: 5,
            created: 0,
            updated: 0,
            errors: [{ row: 2, messages: ['x'] }],
        });

        expect(stats.map((s) => [s.label, s.value, s.alert ?? false])).toEqual([
            ['Filas en el archivo', 5, false],
            ['Por corregir', 1, true],
        ]);
    });
});
