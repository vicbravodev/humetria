import { describe, expect, it } from 'vite-plus/test';
import {
    groupNoticeText,
    groupsFactText,
    groupsShownText,
    hidesSmallGroups,
    lowMinimumWarning,
    stepMinimum,
    NO_GROUP_MINIMUM,
    tooFewText,
    withheldText,
} from '@/lib/group-minimum';

describe('hidesSmallGroups', () => {
    it('hides small groups from a minimum of 2, and with 1 (no minimum) hides none', () => {
        expect(hidesSmallGroups(1)).toBe(false);
        expect(hidesSmallGroups(2)).toBe(true);
        expect(hidesSmallGroups(5)).toBe(true);
        expect(hidesSmallGroups(50)).toBe(true);
    });
});

describe('tooFewText', () => {
    it('says the group is under the minimum, or with no minimum that there are none', () => {
        expect(tooFewText(5, 'respuestas')).toBe('Menos de 5 respuestas');
        expect(tooFewText(12, 'cuestionarios')).toBe(
            'Menos de 12 cuestionarios',
        );
        expect(tooFewText(1, 'respuestas')).toBe('Sin respuestas');
    });
});

describe('NO_GROUP_MINIMUM', () => {
    it('warns that a very small group could be identified, and promises no hiding', () => {
        expect(NO_GROUP_MINIMUM).toBe(
            'Tu empresa no fijó un mínimo de personas por grupo: el resultado de un grupo muy pequeño podría permitir identificar respuestas.',
        );
    });
});

describe('withheldText', () => {
    it('gives why the results are hidden under a minimum, and with none that nothing was answered', () => {
        expect(withheldText(5, 'Menos de 5.')).toBe('Menos de 5.');
        expect(withheldText(1, 'Menos de 1.')).toBe(
            'No se recibieron cuestionarios, así que no hay resultados que mostrar.',
        );
    });
});

describe('groupsShownText', () => {
    it('says which groups show their results, or warns when there is no minimum', () => {
        expect(groupsShownText(8)).toBe(
            'Los resultados solo se muestran con grupos de 8 o más personas.',
        );
        expect(groupsShownText(1)).toBe(NO_GROUP_MINIMUM);
    });
});

describe('groupsFactText', () => {
    it('names the minimum under a title, or that there is none', () => {
        expect(groupsFactText(5)).toBe('Grupos de 5 o más');
        expect(groupsFactText(12)).toBe('Grupos de 12 o más');
        expect(groupsFactText(1)).toBe('Sin mínimo por grupo');
    });
});

describe('groupNoticeText', () => {
    it('tells whoever answers (in usted) the minimum, and with none warns instead of promising', () => {
        expect(groupNoticeText(5)).toBe(
            'Recursos Humanos solo recibe resultados de grupos de 5 o más personas.',
        );
        expect(groupNoticeText(2)).toBe(
            'Recursos Humanos solo recibe resultados de grupos de 2 o más personas.',
        );
        expect(groupNoticeText(1)).toBe(
            'Su empresa no fijó un mínimo de personas por grupo: el resultado de un grupo muy pequeño podría permitir identificar sus respuestas.',
        );
    });
});

describe('lowMinimumWarning', () => {
    it('warns nothing at the recommended minimum or above it', () => {
        expect(lowMinimumWarning(5, 5)).toBeNull();
        expect(lowMinimumWarning(20, 5)).toBeNull();
    });

    it('warns honestly under the recommended minimum', () => {
        expect(lowMinimumWarning(4, 5)).toBe(
            'Con grupos de 4 personas es más fácil adivinar quién respondió qué. Recomendamos 5 o más.',
        );
        expect(lowMinimumWarning(2, 5)).toBe(
            'Con grupos de 2 personas es más fácil adivinar quién respondió qué. Recomendamos 5 o más.',
        );
    });

    it("with no minimum says a group of one shows that person's answer", () => {
        expect(lowMinimumWarning(1, 5)).toBe(
            'Sin mínimo, el resultado de un grupo de una persona mostrará su respuesta, y el de un grupo muy pequeño podría permitir identificar respuestas.',
        );
    });
});

const BOUNDS = { lowest: 1, highest: 50 };

describe('stepMinimum', () => {
    it('moves one person up or down, within the bounds', () => {
        expect(stepMinimum('5', 1, BOUNDS)).toBe('6');
        expect(stepMinimum('5', -1, BOUNDS)).toBe('4');
        expect(stepMinimum('1', -1, BOUNDS)).toBe('1');
        expect(stepMinimum('50', 1, BOUNDS)).toBe('50');
    });

    it('brings what is not a number, or out of bounds, back inside them', () => {
        expect(stepMinimum('', 1, BOUNDS)).toBe('1');
        expect(stepMinimum('abc', -1, BOUNDS)).toBe('1');
        expect(stepMinimum('80', -1, BOUNDS)).toBe('50');
        expect(stepMinimum('2.5', 1, BOUNDS)).toBe('3');
    });
});
