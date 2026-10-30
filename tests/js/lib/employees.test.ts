import { describe, expect, it } from 'vite-plus/test';

import {
    employeeQuery,
    formatMobile,
    importButtonText,
    importSummaryText,
} from '@/lib/employees';

const none = {
    q: '',
    work_center: null,
    area: null,
    status: 'active' as const,
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
                work_center: 'c1',
                area: 'a1',
                status: 'inactive',
            }),
        ).toEqual({
            q: 'ana',
            work_center: 'c1',
            area: 'a1',
            status: 'inactive',
        });
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
