import { describe, expect, it } from 'vitest';
import { keyFindings, pendingText } from '@/lib/report-findings';
import type { AreaResults, RiskGroup, RiskResults } from '@/types';

function group(key: string, levels: number[]): RiskGroup {
    return { key, label: key, category: null, levels };
}

describe('pendingText', () => {
    it('lists what HR has not filled in yet', () => {
        expect(
            pendingText({
                conclusions: null,
                recommendations: null,
                responsible: null,
            }),
        ).toBe(
            'Faltan 3 datos para entregar el informe: conclusiones, recomendaciones y responsable de la evaluación.',
        );
        expect(
            pendingText({
                conclusions: 'Listas.',
                recommendations: null,
                responsible: { name: 'Ana', license: null },
            }),
        ).toBe('Falta 1 dato para entregar el informe: recomendaciones.');
        expect(
            pendingText({
                conclusions: 'Listas.',
                recommendations: 'Listas.',
                responsible: { name: 'Ana', license: null },
            }),
        ).toBeNull();
    });
});

describe('keyFindings', () => {
    const results: RiskResults = {
        answered: 20,
        visible: true,
        final: [2, 2, 2, 4, 10],
        categories: [],
        domains: [
            group('Liderazgo', [2, 0, 3, 3, 12]),
            group('Violencia', [5, 5, 2, 4, 4]),
            group('Relaciones', [10, 5, 2, 3, 0]),
        ],
    };
    const production = {
        id: 'p',
        name: 'Producción',
        answered: 12,
        final: [0, 0, 0, 2, 10],
    };
    const areas: AreaResults = {
        visible: [
            production,
            { id: 'c', name: 'Calidad', answered: 8, final: [2, 2, 2, 2, 0] },
        ],
        hidden: 0,
    };

    it('names the worst domain, the area that concentrates the risk and the strength', () => {
        expect(keyFindings(results, areas)).toEqual([
            {
                tone: 'risk',
                title: 'Liderazgo es el foco principal',
                detail: '15 de 20 cuestionarios (75 %) quedan en alto o muy alto; 12 de ellos en muy alto.',
            },
            {
                tone: 'risk',
                title: 'Producción concentra el riesgo',
                detail: '12 de 12 cuestionarios del área quedan en alto o muy alto.',
            },
            {
                tone: 'strength',
                title: 'Relaciones es la fortaleza',
                detail: 'Solo 15 % en alto o muy alto. Es la base para construir el programa.',
            },
        ]);
    });

    it('leaves out the area with only one shown, and the clause on Muy alto when there is none', () => {
        const calmer: RiskResults = {
            ...results,
            domains: [
                group('Liderazgo', [10, 5, 0, 5, 0]),
                group('Relaciones', [20, 0, 0, 0, 0]),
            ],
        };

        expect(
            keyFindings(calmer, { visible: [production], hidden: 1 }),
        ).toEqual([
            {
                tone: 'risk',
                title: 'Liderazgo es el foco principal',
                detail: '5 de 20 cuestionarios (25 %) quedan en alto o muy alto.',
            },
            {
                tone: 'strength',
                title: 'Relaciones es la fortaleza',
                detail: 'Ningún cuestionario en alto o muy alto. Es la base para construir el programa.',
            },
        ]);
    });

    it('says so when no domain has anyone in Alto or Muy alto', () => {
        const calm: RiskResults = {
            ...results,
            final: [10, 8, 2, 0, 0],
            domains: [group('Liderazgo', [10, 8, 2, 0, 0])],
        };

        expect(keyFindings(calm, { visible: [], hidden: 0 })).toEqual([
            {
                tone: 'strength',
                title: 'Ningún dominio en riesgo alto o muy alto',
                detail: 'Ningún cuestionario queda en riesgo alto o muy alto; 2 de 20 (10 %) quedan en riesgo medio.',
            },
        ]);
    });
});
