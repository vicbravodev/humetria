import { describe, expect, it } from 'vite-plus/test';
import { answeredLine, themesLine } from '@/lib/share-results';

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
