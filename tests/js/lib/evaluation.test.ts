import { describe, expect, it } from 'vite-plus/test';
import {
    addDays,
    arcCaption,
    barHeight,
    dayAnswersText,
    busiestDay,
    dateRange,
    dayOf,
    headerFacts,
    historyItems,
    daysLeft,
    laggingArea,
    monthOf,
    peopleText,
    pendingTotal,
    remindable,
    percent,
    staffBand,
    visibleStandings,
    weekChoices,
} from '@/lib/evaluation';
import type { AreaParticipation, CampaignDetail } from '@/types';

describe('dateRange', () => {
    it('writes the year once, and the month once when it is the same', () => {
        expect(dateRange('27 oct 2026', '10 nov 2026')).toBe(
            '27 oct al 10 nov 2026',
        );
        expect(dateRange('10 oct 2026', '29 oct 2026')).toBe(
            '10 al 29 oct 2026',
        );
        expect(dateRange('28 dic 2026', '8 ene 2027')).toBe(
            '28 dic 2026 al 8 ene 2027',
        );
    });
});

describe('staffBand', () => {
    it('groups the people as the norm does, at its exact limits', () => {
        expect(staffBand(15)).toBe('hasta 15 personas');
        expect(staffBand(16)).toBe('de 16 a 50 personas');
        expect(staffBand(50)).toBe('de 16 a 50 personas');
        expect(staffBand(51)).toBe('más de 50 personas');
    });
});

describe('addDays and weekChoices', () => {
    it('moves a date across months, and leaves what is not a date', () => {
        expect(addDays('2026-10-28', 7)).toBe('2026-11-04');
        expect(addDays('2026-11-04', -7)).toBe('2026-10-28');
        expect(addDays('nada', 7)).toBe('nada');
    });

    it('offers one, two and three weeks around the suggested two', () => {
        expect(weekChoices('2026-11-13')).toEqual([
            { label: '1 semana', date: '2026-11-06' },
            { label: '2 semanas', date: '2026-11-13' },
            { label: '3 semanas', date: '2026-11-20' },
        ]);
    });
});

describe('percent', () => {
    it('rounds to a whole percent, 0 of nothing', () => {
        expect(percent(6, 14)).toBe(43);
        expect(percent(1, 2)).toBe(50);
        expect(percent(3, 0)).toBe(0);
    });
});

describe('laggingArea', () => {
    const area = (
        name: string,
        [answered, pending, total]: [number, number, number],
    ): AreaParticipation => ({ id: name, name, answered, pending, total });

    it('picks the smallest share answered among the areas with someone to remind', () => {
        expect(
            laggingArea([
                area('Administración', [6, 8, 14]),
                area('Ventas', [8, 30, 38]),
                area('Almacén', [0, 0, 4]),
                area('Dirección', [4, 4, 8]),
            ])?.name,
        ).toBe('Ventas');
        expect(laggingArea([area('Ventas', [2, 0, 2])])).toBeNull();
        expect(laggingArea([])).toBeNull();
    });
});

describe('arcCaption', () => {
    it('says what the figure counts against', () => {
        expect(
            arcCaption({ completed: 18, target: 60, againstSample: false }),
        ).toBe('de 60 contestaron');
        expect(
            arcCaption({ completed: 18, target: 53, againstSample: true }),
        ).toBe('de 53 de la muestra');
    });
});

describe('the days of the chart', () => {
    const days = [
        { date: '2026-10-30', answers: 4 },
        { date: '2026-10-31', answers: 0 },
        { date: '2026-11-01', answers: null },
        { date: '2026-11-02', answers: null },
    ];

    it('counts the days left and the busiest one', () => {
        expect(daysLeft(days)).toBe('Quedan 2 días');
        expect(daysLeft(days.slice(0, 3))).toBe('Queda 1 día');
        expect(daysLeft(days.slice(0, 2))).toBe('Hoy es el último día');
        expect(busiestDay(days)).toBe(4);
        expect(busiestDay([{ date: '2026-11-01', answers: null }])).toBe(1);
    });

    it('names the day and the month', () => {
        expect(dayOf('2026-11-02')).toBe(2);
        expect(monthOf('2026-10-30')).toBe('octubre');
        expect(monthOf('nada')).toBe('');
    });
});

describe('visibleStandings', () => {
    const standings = [
        { value: 'pending', label: 'Pendientes', count: 0 },
        { value: 'unreachable', label: 'Sin correo', count: 2 },
        { value: 'failed', label: 'Envío fallido', count: 0 },
        { value: 'answered', label: 'Contestaron', count: 0 },
        { value: 'cancelled', label: 'Canceladas', count: 0 },
        { value: 'all', label: 'Todas', count: 2 },
    ];

    it('keeps the three main views, and the others with people or in view', () => {
        expect(
            visibleStandings(standings, 'failed').map((s) => s.value),
        ).toEqual(['pending', 'unreachable', 'failed', 'answered', 'all']);
    });
});

describe('peopleText', () => {
    it('agrees in number', () => {
        expect(peopleText(1)).toBe('1 persona');
        expect(peopleText(28)).toBe('28 personas');
    });
});

describe('headerFacts and historyItems', () => {
    const campaign = (changes: Partial<CampaignDetail>): CampaignDetail => ({
        id: 'c1',
        workCenter: 'Oficinas Monterrey',
        kind: 'nom035_evaluation',
        kindLabel: 'Evaluación NOM-035',
        status: 'active',
        statusLabel: 'En curso',
        instruments: ['Guía I', 'Guía III'],
        closesOn: '10 nov 2026',
        closedOn: null,
        progress: null,
        workers: 60,
        population: 'sample',
        populationLabel: 'Muestra',
        sampleSize: 52,
        quotas: [],
        launchedOn: '27 oct 2026',
        workCenterId: 'w1',
        createdOn: '20 oct 2026',
        nextDueBy: null,
        ...changes,
    });

    it('tells a closed evaluation from its draft to the next one due', () => {
        expect(
            historyItems(
                campaign({
                    status: 'closed',
                    closedOn: '29 oct 2026',
                    nextDueBy: '29 oct 2028',
                    progress: {
                        completed: 29,
                        target: 30,
                        againstSample: false,
                    },
                }),
            ),
        ).toEqual([
            { title: 'Borrador creado', date: '20 oct 2026', done: true },
            { title: 'Lanzada', date: '27 oct 2026', done: true },
            { title: 'Cerrada · 29 de 30', date: '29 oct 2026', done: true },
            {
                title: 'Siguiente evaluación',
                date: 'antes del 29 oct 2028',
                done: false,
            },
        ]);
    });

    it('starts a Guía I campaign launched, with no next one', () => {
        expect(
            historyItems(
                campaign({
                    kind: 'nom035_trauma',
                    closedOn: '29 oct 2026',
                    nextDueBy: '29 oct 2028',
                }),
            ).map((item) => item.title),
        ).toEqual(['Lanzada', 'Cerrada']);
    });

    it('gives the people, the guides with the sample and the period', () => {
        expect(headerFacts(campaign({}))).toEqual([
            { kind: 'people', text: '60 personas' },
            { kind: 'guides', text: 'Guías I y III (muestra de 52)' },
            { kind: 'period', text: '27 oct al 10 nov 2026' },
        ]);
    });

    it('ends a closed one on the day it closed, and leaves out what is not known', () => {
        expect(
            headerFacts(
                campaign({ closedOn: '29 oct 2026', sampleSize: null }),
            ).at(-1),
        ).toEqual({ kind: 'period', text: '27 al 29 oct 2026' });
        expect(
            headerFacts(
                campaign({ workers: null, launchedOn: null, sampleSize: null }),
            ),
        ).toEqual([{ kind: 'guides', text: 'Guías I y III' }]);
    });
});

describe('barHeight and dayAnswersText', () => {
    it('draws a day against the busiest, and says it', () => {
        expect(barHeight(7, 7)).toBe(98);
        expect(barHeight(2, 7)).toBe(28);
        expect(barHeight(1, 100)).toBe(4);
        expect(barHeight(0, 7)).toBe(2);
        expect(dayAnswersText(null)).toBe('por venir');
        expect(dayAnswersText(1)).toBe('1 persona terminó');
        expect(dayAnswersText(0)).toBe('0 personas terminaron');
    });
});

describe('remindable', () => {
    it('reminds pending people or those whose link failed, when there is someone', () => {
        expect(remindable('pending', 3)).toBe(true);
        expect(remindable('failed', 1)).toBe(true);
        expect(remindable('pending', 0)).toBe(false);
        expect(remindable('answered', 3)).toBe(false);
    });
});

describe('pendingTotal', () => {
    it('adds up who waits in every area', () => {
        expect(
            pendingTotal([
                { id: 'a', name: 'A', answered: 1, pending: 2, total: 4 },
                { id: 'b', name: 'B', answered: 0, pending: 3, total: 3 },
            ]),
        ).toBe(5);
        expect(pendingTotal(null)).toBe(0);
    });
});
