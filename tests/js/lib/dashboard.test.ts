import { describe, expect, it } from 'vite-plus/test';
import {
    greeting,
    nextStep,
    normsInUse,
    unansweredCenters,
    panelSummary,
    stageLine,
    stageStates,
} from '@/lib/dashboard';
import type { CenterRoutes, RouteProgress } from '@/types';

const at = (stage: number, status = 'define las reglas'): RouteProgress => ({
    applies: true,
    done: [1, 2, 3, 4, 5, 6].map((number) => number !== stage),
    status,
    answered: true,
});
const notApplicable: RouteProgress = {
    applies: false,
    done: [],
    status: 'Nadie teletrabaja más del 40 % · constancia del 3 oct 2026',
    answered: true,
};
const unanswered: RouteProgress = {
    applies: false,
    done: [],
    status: 'Falta contestar',
    answered: false,
};
const allDone: RouteProgress = {
    applies: true,
    done: [true, true, true, true, true, true],
    status: 'al día hasta el 29 oct 2028',
    answered: true,
};
const center = (
    name: string,
    routes: Partial<Omit<CenterRoutes, 'id' | 'name'>>,
): CenterRoutes => ({
    id: name,
    name,
    nom035: allDone,
    nom037: notApplicable,
    seating: notApplicable,
    ...routes,
});

describe('greeting', () => {
    it('greets by the first name', () => {
        expect(greeting('Rita Ramírez Hernández')).toBe('Hola, Rita');
        expect(greeting('  Rita ')).toBe('Hola, Rita');
        expect(greeting('')).toBe('Hola');
    });

    it('skips a title before the name, but not a shortened name', () => {
        expect(greeting('Dr. Guillermo Delgado')).toBe('Hola, Guillermo');
        expect(greeting('lic Ana Pérez')).toBe('Hola, Ana');
        expect(greeting('Mtra. Dra. Sofía Ruiz')).toBe('Hola, Sofía');
        expect(greeting('Ma. Elena Pérez')).toBe('Hola, Ma.');
        expect(greeting('Ing.')).toBe('Hola');
    });
});

describe('panelSummary', () => {
    it('says how many standards apply in how many centers, and what comes first', () => {
        expect(panelSummary({ norms: 3, centers: 2 })).toBe(
            'Llevas 3 normas en 2 centros. Primero lo urgente, luego dónde vas en cada una.',
        );
        expect(panelSummary({ norms: 1, centers: 1 })).toBe(
            'Llevas 1 norma en 1 centro. Primero lo urgente, luego dónde vas en cada una.',
        );
        expect(panelSummary({ norms: 0, centers: 0 })).toBe(
            'Aún no registras centros de trabajo.',
        );
    });
});

describe('stageStates', () => {
    it('marks the stages done, the earliest one left as the current one, and the rest pending', () => {
        expect(
            stageStates({
                applies: true,
                done: [false, true, false, true, false, false],
                status: '',
                answered: true,
            }),
        ).toEqual(['current', 'done', 'pending', 'done', 'pending', 'pending']);
        expect(stageStates(allDone)).toEqual(Array(6).fill('done'));
    });
});

describe('stageLine', () => {
    it('numbers the stage before its line, or says it plainly when every stage is done or the standard does not apply', () => {
        expect(stageLine(at(2, '18 de 60 contestaron'))).toBe(
            'Etapa 2 de 6 · 18 de 60 contestaron',
        );
        expect(stageLine(allDone)).toBe('Al día hasta el 29 oct 2028');
        expect(stageLine(notApplicable)).toBe(
            'Nadie teletrabaja más del 40 % · constancia del 3 oct 2026',
        );
    });
});

describe('normsInUse', () => {
    // Rediseño v2 (decisión A2): a standard that applies nowhere stays, with the certificate of each center in its place.
    it('lists every standard with every center, and none without centers', () => {
        const plant = center('Planta', { nom037: at(1) });
        const offices = center('Oficinas', { seating: unanswered });

        expect(
            normsInUse([plant, offices]).map(({ norm, rows }) => [
                norm.key,
                rows.map((row) => row.name),
            ]),
        ).toEqual([
            ['nom035', ['Planta', 'Oficinas']],
            ['nom037', ['Planta', 'Oficinas']],
            ['seating', ['Planta', 'Oficinas']],
        ]);
        expect(normsInUse([])).toEqual([]);
    });
});

describe('unansweredCenters', () => {
    it('counts the centers that still have to answer «¿Te aplica?» for some standard', () => {
        expect(
            unansweredCenters([
                center('Planta', { seating: unanswered }),
                center('Oficinas', { nom037: unanswered, seating: unanswered }),
                center('Bodega', {}),
            ]),
        ).toBe(2);
    });
});

describe('nextStep', () => {
    it('recommends the earliest stage left of all, the first standard winning a tie, with how many centers it covers', () => {
        const step = nextStep([
            center('Planta', { nom035: at(5), nom037: at(1), seating: at(3) }),
            center('Oficinas', { nom035: at(2), nom037: at(1) }),
        ]);

        expect(step).toEqual({
            norm: 'nom037',
            stage: 1,
            centers: 2,
            title: 'Define las reglas del teletrabajo',
            text: 'Tu Política de Teletrabajo es la base: dice horarios, desconexión y cómo se regresa a la oficina. Sin ella, lo demás no se sostiene.',
        });
        expect(nextStep([center('Planta', { nom035: at(2) })])?.norm).toBe(
            'nom035',
        );
    });

    it('has nothing to recommend when every route that applies is done', () => {
        expect(nextStep([center('Planta', {})])).toBeNull();
        expect(nextStep([])).toBeNull();
    });
});

describe('standards the company does not have', () => {
    const modules = ['nom035', 'seating'];
    const centers = [center('Planta', { nom037: at(1), seating: at(3) })];

    it('are not listed in the routes', () => {
        expect(
            normsInUse(centers, modules).map(({ norm }) => norm.key),
        ).toEqual(['nom035', 'seating']);
    });

    it('are not asked about, nor recommended as the next step', () => {
        expect(
            unansweredCenters(
                [center('Planta', { nom037: unanswered })],
                modules,
            ),
        ).toBe(0);
        expect(nextStep(centers, ['nom035', 'seating'])?.norm).toBe('seating');
        expect(nextStep(centers, ['nom035'])).toBeNull();
    });
});
