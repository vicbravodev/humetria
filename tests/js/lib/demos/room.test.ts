import { describe, expect, it } from 'vite-plus/test';

import {
    moveTour,
    preparationPhase,
    roomDescription,
    sessionMinutes,
    sessionTitle,
    toggleTour,
    tourNames,
} from '@/lib/demos/room';
import type { DemoRoomSession, DemoTour } from '@/types';

const tours: DemoTour[] = [
    { key: 'nom035', title: 'NOM-035', summary: '', minutes: 12 },
    { key: 'attendance', title: 'Asistencia', summary: '', minutes: 10 },
    { key: 'humi', title: 'Humi', summary: '', minutes: 3 },
];

describe('the tours of a new session', () => {
    it('adds a tour at the end and takes it out again', () => {
        expect(toggleTour(['nom035'], 'humi', true)).toEqual([
            'nom035',
            'humi',
        ]);
        expect(toggleTour(['nom035', 'humi'], 'nom035', false)).toEqual([
            'humi',
        ]);
        expect(toggleTour(['nom035'], 'nom035', true)).toEqual(['nom035']);
    });

    it('moves a tour up or down, and leaves it at the edges', () => {
        const order = ['nom035', 'attendance', 'humi'];

        expect(moveTour(order, 'attendance', -1)).toEqual([
            'attendance',
            'nom035',
            'humi',
        ]);
        expect(moveTour(order, 'attendance', 1)).toEqual([
            'nom035',
            'humi',
            'attendance',
        ]);
        expect(moveTour(order, 'nom035', -1)).toEqual(order);
        expect(moveTour(order, 'humi', 1)).toEqual(order);
        expect(moveTour(order, 'leave', 1)).toEqual(order);
    });

    it('adds up the minutes of the chosen tours and of the one that always goes', () => {
        expect(sessionMinutes(tours, ['nom035', 'attendance'])).toBe(26);
        expect(sessionMinutes(tours, [])).toBe(4);
    });
});

describe('the preparation of a session', () => {
    it('tells which of its phases goes on from the step of the run', () => {
        expect(preparationPhase(null)).toBe(0);
        expect(preparationPhase('Borrando la demo')).toBe(0);
        expect(preparationPhase('Organización')).toBe(1);
        expect(preparationPhase('Teletrabajo')).toBe(1);
        expect(preparationPhase('Sala de la sesión')).toBe(2);
    });
});

describe('the name of a session', () => {
    it('names the client, or says it has none', () => {
        expect(sessionTitle('Grupo Ejemplo')).toBe('Sesión con Grupo Ejemplo');
        expect(sessionTitle(null)).toBe('Sesión de demostración');
    });
});

describe('the tours a session shows', () => {
    it('names the chosen ones, leaving out the one that always goes', () => {
        const tour = (key: string, title: string) => ({
            key,
            title,
            done: 0,
            total: 2,
        });

        expect(
            tourNames([
                tour('base', 'El centro y su gente'),
                tour('nom035', 'NOM-035'),
                tour('attendance', 'Asistencia'),
                tour('humi', 'Humi'),
            ]),
        ).toBe('NOM-035, Asistencia y Humi');
        expect(tourNames([tour('base', 'El centro y su gente')])).toBe(
            'El centro y su gente',
        );
    });
});

describe("the sentence under the room's title", () => {
    const company = { id: 'c1', name: 'Manufacturas Alba del Bajío' };
    const prepared = {
        ago: 'hace 1 día',
        at: '25 nov 2026, 18:40',
        stale: false,
    };
    const session = (
        status: DemoRoomSession['status'],
        client: string | null = 'Grupo Ejemplo',
    ): DemoRoomSession => ({
        client,
        status,
        failed: status === 'failed',
        since: 'hace 12 minutos',
        tours: [
            { key: 'base', title: 'El centro y su gente', done: 0, total: 2 },
            { key: 'nom035', title: 'NOM-035', done: 0, total: 6 },
            { key: 'attendance', title: 'Asistencia', done: 0, total: 7 },
        ],
        next: null,
    });

    it('says how old the demo is without a session', () => {
        expect(
            roomDescription({ company: null, prepared: null, session: null }),
        ).toBe('La empresa demo para enseñar Humetría en vivo.');
        expect(
            roomDescription({ company, prepared: null, session: null }),
        ).toBe('Manufacturas Alba del Bajío');
        expect(roomDescription({ company, prepared, session: null })).toBe(
            'Manufacturas Alba del Bajío · preparada hace 1 día (25 nov 2026, 18:40)',
        );
    });

    it('says where the session stands, with its client or without', () => {
        const at = (status: DemoRoomSession['status'], client?: null) =>
            roomDescription({
                company,
                prepared,
                session: session(status, client),
            });

        expect(at('preparing')).toBe(
            'Preparando la sesión de Grupo Ejemplo · NOM-035 y Asistencia',
        );
        expect(at('preparing', null)).toBe(
            'Preparando la sesión · NOM-035 y Asistencia',
        );
        expect(at('active')).toBe(
            'Sesión en curso con Grupo Ejemplo · empezó hace 12 minutos',
        );
        expect(at('active', null)).toBe(
            'Sesión en curso · empezó hace 12 minutos',
        );
        expect(at('ending')).toBe('Terminando la sesión con Grupo Ejemplo');
        expect(at('failed')).toBe('La sesión con Grupo Ejemplo no quedó lista');
        expect(at('failed', null)).toBe('La sesión no quedó lista');
    });
});
