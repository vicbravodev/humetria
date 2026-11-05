import { describe, expect, it } from 'vite-plus/test';

import { centersIntro, nextInCenter, stagesDone } from '@/lib/telework/centers';
import type { CenterCard } from '@/types';

const card: CenterCard = {
    id: 'c1',
    name: 'Planta Querétaro',
    activity: 'Manufactura',
    teleworkers: 3,
    aspirants: 0,
    pendingReviews: 0,
    done: 4,
    applicable: 17,
    route: {
        applies: true,
        done: [false, true, false, true, false, false],
        status: 'define las reglas',
        answered: true,
    },
    accident: null,
};

describe('centersIntro', () => {
    it('counts people and centers', () => {
        expect(centersIntro({ teleworkers: 5, centers: 2 })).toBe(
            '5 personas en 2 centros trabajan desde casa más del 40 % del tiempo. Algunas cosas se hacen una vez para toda la empresa; otras, en cada centro.',
        );
        expect(centersIntro({ teleworkers: 1, centers: 1 })).toContain(
            '1 persona en 1 centro trabaja desde casa',
        );
    });
});

describe('nextInCenter', () => {
    it('puts the accident first, with the lists that wait', () => {
        expect(
            nextInCenter({ ...card, accident: 'Hugo Saiz', pendingReviews: 2 }),
        ).toBe(
            'Revisar el aviso de accidente de Hugo Saiz y 2 listas de lugares',
        );
        expect(nextInCenter({ ...card, accident: 'Hugo Saiz' })).toBe(
            'Revisar el aviso de accidente de Hugo Saiz',
        );
    });

    it('then the lists the Comisión has to review', () => {
        expect(nextInCenter({ ...card, pendingReviews: 1 })).toBe(
            'Que la Comisión revise 1 lista de lugares',
        );
    });

    it('then the stage, and the question where the center has not answered', () => {
        expect(nextInCenter(card)).toBe('Define las reglas');
        expect(
            nextInCenter({
                ...card,
                route: {
                    applies: false,
                    done: [],
                    status: 'Falta contestar',
                    answered: false,
                },
            }),
        ).toBe('Contesta si alguien teletrabaja en este centro');
    });
});

describe('stagesDone', () => {
    it('counts the stages done', () => {
        expect(stagesDone(card)).toBe('2 de 6 etapas');
    });
});
