import { describe, expect, it } from 'vite-plus/test';
import {
    answeredDetail,
    answeredLine,
    peopleResultsText,
    themesLine,
} from '@/lib/share-results';

describe('answeredLine', () => {
    it('says how many answered, and below the minimum only that they were fewer', () => {
        expect(answeredLine(30, 5, 'Oficina')).toBe(
            'Contestaron 30 personas de Oficina. Gracias por su tiempo.',
        );
        expect(answeredLine(1, 5, 'Oficina')).toContain('1 persona de');
        expect(answeredLine(null, 5, 'Oficina')).toBe(
            'Contestaron menos de 5 personas de Oficina: con tan pocas no se muestran cifras.',
        );
    });

    it('with no minimum, says nobody answered instead of «menos de 1»', () => {
        expect(answeredLine(null, 1, 'Oficina')).toBe(
            'Nadie de Oficina contestó: no hay cifras que mostrar.',
        );
        expect(answeredLine(1, 1, 'Oficina')).toContain('1 persona de');
    });
});

describe('answeredDetail', () => {
    it('gives the count, or below the minimum only that they were fewer, or that nobody answered with none', () => {
        expect(answeredDetail(12, 5)).toBe('12');
        expect(answeredDetail(null, 5)).toBe('Menos de 5');
        expect(answeredDetail(null, 12)).toBe('Menos de 12');
        expect(answeredDetail(null, 1)).toBe('Nadie contestó');
    });
});

describe('peopleResultsText', () => {
    it('promises the minimum of the evaluation, or warns when it has none', () => {
        expect(peopleResultsText(5)).toBe(
            'Verán cuántas personas contestaron y los temas que más atención necesitan. Nunca datos de áreas o grupos con menos de 5 personas.',
        );
        expect(peopleResultsText(2)).toContain('con menos de 2 personas.');
        expect(peopleResultsText(1)).toBe(
            'Verán cuántas personas contestaron y los temas que más atención necesitan, del centro completo y nunca por área. Tu empresa no fijó un mínimo de personas por grupo: el resultado de un grupo muy pequeño podría permitir identificar respuestas.',
        );
    });
});

describe('themesLine', () => {
    it('names the themes in one sentence, or nothing without them', () => {
        expect(
            themesLine(['Violencia', 'Carga de trabajo', 'Jornada de trabajo']),
        ).toBe('Violencia, carga de trabajo y jornada de trabajo.');
        expect(themesLine(['Liderazgo'])).toBe('Liderazgo.');
        expect(themesLine([])).toBeNull();
    });
});
