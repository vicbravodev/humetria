import { describe, expect, it } from 'vite-plus/test';
import { parseToolCard } from '@/lib/assistant/tool-cards';

const base = {
    title: 'Resultados',
    link: '/admin/acme/campaigns/1/results',
    note: null,
    truncated: false,
};

describe('parseToolCard: risk and progress', () => {
    it('draws the people in each risk level of a closed evaluation', () => {
        const card = parseToolCard(
            JSON.stringify({
                ...base,
                render: 'risk_levels',
                minGroup: 5,
                rows: [
                    {
                        kind: 'final',
                        label: 'Calificación final',
                        answered: 6,
                        levels: { 'Nulo o despreciable': 0, Bajo: 1, Medio: 5 },
                    },
                    {
                        kind: 'domain',
                        label: 'Carga de trabajo',
                        levels: { Alto: 2, raro: 'x' },
                    },
                    { label: null },
                ],
            }),
        );

        expect(card).toEqual({
            ...base,
            render: 'risk_levels',
            minGroup: 5,
            rows: [
                {
                    kind: 'final',
                    label: 'Calificación final',
                    answered: 6,
                    levels: [
                        { label: 'Nulo o despreciable', count: 0 },
                        { label: 'Bajo', count: 1 },
                        { label: 'Medio', count: 5 },
                    ],
                },
                {
                    kind: 'domain',
                    label: 'Carga de trabajo',
                    answered: null,
                    levels: [{ label: 'Alto', count: 2 }],
                },
            ],
        });
    });

    it('draws how the campaigns go: who was given each guide and how many answered', () => {
        const card = parseToolCard({
            ...base,
            render: 'progress',
            rows: [
                {
                    label: 'Planta Apodaca · Evaluación NOM-035',
                    status: 'active',
                    closes_on: '14 nov 2026',
                    days_left: 12,
                    guides: [
                        { label: 'Guía III', invited: 186, answered: 142 },
                        { invited: 1 },
                    ],
                    link: '/x',
                },
            ],
        });

        expect(card).toMatchObject({
            render: 'progress',
            rows: [
                {
                    label: 'Planta Apodaca · Evaluación NOM-035',
                    status: 'active',
                    closesOn: '14 nov 2026',
                    daysLeft: 12,
                    guides: [
                        { label: 'Guía III', invited: 186, answered: 142 },
                    ],
                    link: '/x',
                },
            ],
        });
    });
});

describe('parseToolCard: status and lists', () => {
    it('draws where each norm stands, and drops a row whose state it does not know', () => {
        const card = parseToolCard(
            JSON.stringify({
                ...base,
                render: 'status_list',
                rows: [
                    {
                        key: 'nom035',
                        label: 'NOM-035',
                        state: 'in_progress',
                        stateLabel: 'En curso',
                        summary: 'De 1 centro',
                        link: '/c',
                    },
                    { key: 'x', label: 'Rara', state: 'maybe' },
                ],
            }),
        );

        expect(card).toMatchObject({
            render: 'status_list',
            rows: [
                {
                    key: 'nom035',
                    label: 'NOM-035',
                    state: 'in_progress',
                    stateLabel: 'En curso',
                    summary: 'De 1 centro',
                    link: '/c',
                },
            ],
        });
        expect(card?.rows).toHaveLength(1);
    });

    it('draws a list of pending items, and a norm criterion by its text', () => {
        const pending = parseToolCard({
            ...base,
            render: 'list',
            rows: [
                {
                    title: 'Evaluar NOM-035 en Planta',
                    detail: 'Venció',
                    due: '1 ene 2026',
                    link: '/campaigns',
                },
            ],
        });
        const criterion = parseToolCard({
            ...base,
            render: 'list',
            rows: [{ title: 'Mínimo de personas', text: 'Ningún resultado…' }],
        });

        expect(pending).toMatchObject({
            rows: [
                {
                    title: 'Evaluar NOM-035 en Planta',
                    detail: 'Venció',
                    due: '1 ene 2026',
                    link: '/campaigns',
                },
            ],
        });
        expect(criterion).toMatchObject({
            rows: [
                {
                    title: 'Mínimo de personas',
                    detail: 'Ningún resultado…',
                    due: null,
                    link: null,
                },
            ],
        });
    });

    it('keeps the note and whether rows were left out', () => {
        const card = parseToolCard({
            title: 'Pendientes',
            render: 'list',
            rows: [],
            link: null,
            note: 'Muestro 15 de 17 pendientes.',
            truncated: true,
        });

        expect(card).toMatchObject({
            note: 'Muestro 15 de 17 pendientes.',
            truncated: true,
            rows: [],
        });
    });
});

describe('parseToolCard: criteria of a norm', () => {
    it('reads which criterion a row is, and where it comes from', () => {
        const card = parseToolCard({
            ...base,
            render: 'list',
            rows: [
                {
                    norm: 'NOM-037',
                    id: 'C-01',
                    title: 'Aplica a quien teletrabaja',
                    source: 'Norma + STPS',
                    text: '**Qué hacemos.** Algo.',
                },
                { title: 'Un pendiente' },
            ],
        });

        expect(card).toMatchObject({
            rows: [
                { ref: 'NOM-037 · C-01', source: 'Norma + STPS' },
                { ref: null, source: null },
            ],
        });
    });
});

describe('parseToolCard: what is no card', () => {
    it('is nothing for what is not a card of Humi', () => {
        expect(parseToolCard('no es json')).toBeNull();
        expect(parseToolCard('[1,2]')).toBeNull();
        expect(parseToolCard(null)).toBeNull();
        expect(parseToolCard(7)).toBeNull();
        expect(parseToolCard({ render: 'list' })).toBeNull();
        expect(
            parseToolCard({ title: 'X', render: 'otra', rows: [] }),
        ).toBeNull();
    });
});
