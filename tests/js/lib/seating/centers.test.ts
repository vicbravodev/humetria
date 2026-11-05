import { describe, expect, it } from 'vite-plus/test';

import { centersIntro, nextInCenter } from '@/lib/seating/centers';
import type { SeatingCenterCard } from '@/types';

const card: SeatingCenterCard = {
    id: 'c1',
    name: 'Planta Querétaro',
    activity: 'Manufactura',
    people: 9,
    route: {
        applies: true,
        done: [true, true, false, true, true, false],
        status: 'evalúa a 3 personas',
        answered: true,
    },
};

describe('centersIntro', () => {
    it('counts the people who work standing and their centers', () => {
        expect(centersIntro({ people: 9, centers: 2 })).toBe(
            '9 personas en 2 centros trabajan de pie. Cada centro lleva su ruta: puestos, asientos, evaluaciones y reglamento.',
        );
        expect(centersIntro({ people: 1, centers: 1 })).toContain(
            '1 persona en 1 centro trabaja de pie.',
        );
    });

    it('says nobody works standing yet', () => {
        expect(centersIntro({ people: 0, centers: 0 })).toBe(
            'Todavía nadie trabaja de pie en tus centros. Cada centro lleva su ruta: puestos, asientos, evaluaciones y reglamento.',
        );
    });
});

describe('nextInCenter', () => {
    it('says what comes next on the route', () => {
        expect(nextInCenter(card)).toBe('Evalúa a 3 personas');
    });

    it('asks a center that never answered, and closes one where it does not apply', () => {
        expect(
            nextInCenter({
                ...card,
                route: { ...card.route, applies: false, answered: false },
            }),
        ).toBe('Contesta si alguien trabaja de pie en este centro');
        expect(
            nextInCenter({
                ...card,
                route: {
                    applies: false,
                    done: [],
                    status: 'Nadie trabaja de pie · constancia del 3 oct 2026',
                    answered: true,
                },
            }),
        ).toBe('Nadie trabaja de pie · constancia del 3 oct 2026');
    });
});
