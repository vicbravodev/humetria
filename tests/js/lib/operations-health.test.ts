import { describe, expect, it } from 'vite-plus/test';

import {
    activeAlerts,
    barsSummary,
    hourBars,
    splitServices,
    withBars,
} from '@/lib/operations-health';
import type { Service } from '@/lib/operations';
import type { HourTraffic } from '@/types';

const hour = (
    startsAt: string,
    whatsapp: { total: number; rejected: number },
): HourTraffic => ({
    startsAt,
    mail: { total: 0, rejected: 0 },
    whatsapp,
});

const ok = (name: string): Service => ({
    name,
    detail: 'Todo bien',
    state: { variant: 'success', label: 'Operativo' },
});

describe('hourBars', () => {
    it('draws an hour without messages idle, at or under the limit fine, and over it as a warning, in Mexico City time', () => {
        const bars = hourBars(
            [
                hour('2026-11-25T16:00:00+00:00', { total: 0, rejected: 0 }),
                hour('2026-11-25T17:00:00+00:00', { total: 20, rejected: 1 }),
                hour('2026-11-25T18:00:00+00:00', { total: 10, rejected: 1 }),
            ],
            'whatsapp',
            5,
        );

        expect(bars).toEqual([
            { label: '10:00 · sin mensajes', tone: 'idle' },
            { label: '11:00 · 20 enviados, 1 rechazados', tone: 'ok' },
            { label: '12:00 · 10 enviados, 1 rechazados', tone: 'warning' },
        ]);
    });

    it('reads the channel it is asked for', () => {
        expect(
            hourBars(
                [hour('2026-11-25T18:00:00+00:00', { total: 3, rejected: 3 })],
                'mail',
                5,
            )[0]?.tone,
        ).toBe('idle');
    });
});

describe('withBars', () => {
    it('gives mail and WhatsApp their hours, and the services without a history none', () => {
        const hours = [
            hour('2026-11-25T18:00:00+00:00', { total: 1, rejected: 1 }),
        ];
        const rows = withBars(
            [
                ok('Colas (Horizon)'),
                ok('Correo (Resend)'),
                ok('WhatsApp (Meta)'),
            ],
            { hours, rejectionLimit: 5 },
        );

        expect(rows.map(({ bars }) => bars?.[0]?.tone ?? null)).toEqual([
            null,
            'idle',
            'warning',
        ]);
    });
});

describe('barsSummary', () => {
    it('says how many hours went over the limit, for whoever does not see the colors', () => {
        expect(barsSummary([{ label: '', tone: 'ok' }], 5)).toBe(
            'Últimas 24 horas: ninguna hora pasó del 5 % de rechazos.',
        );
        expect(
            barsSummary(
                [
                    { label: '', tone: 'warning' },
                    { label: '', tone: 'idle' },
                ],
                5,
            ),
        ).toBe('Últimas 24 horas: 1 hora pasó del 5 % de rechazos.');
        expect(
            barsSummary(
                [
                    { label: '', tone: 'warning' },
                    { label: '', tone: 'warning' },
                ],
                5,
            ),
        ).toBe('Últimas 24 horas: 2 horas pasaron del 5 % de rechazos.');
    });
});

describe('splitServices', () => {
    it('takes the scheduler out of the services, for its own card', () => {
        const scheduler = { ...ok('Programador de tareas') };
        const { rows, scheduler: found } = splitServices([
            ok('Colas (Horizon)'),
            scheduler,
            ok('Correo (Resend)'),
        ]);

        expect(rows.map(({ name }) => name)).toEqual([
            'Colas (Horizon)',
            'Correo (Resend)',
        ]);
        expect(found).toBe(scheduler);
    });
});

describe('activeAlerts', () => {
    it('raises one alert per service in trouble and one for the jobs that failed today', () => {
        const paused: Service = {
            name: 'WhatsApp (Meta)',
            detail: 'No se envía: se pausó solo: demasiados rechazos.',
            state: { variant: 'warning', label: 'En pausa' },
        };

        expect(activeAlerts([ok('Colas (Horizon)'), paused], 2)).toEqual([
            {
                title: 'WhatsApp (Meta): en pausa',
                detail: 'No se envía: se pausó solo: demasiados rechazos.',
                tone: 'warning',
            },
            {
                title: '2 trabajos fallaron hoy',
                detail: 'Ya no se reintentan solos: se revisan en Horizon.',
                tone: 'warning',
            },
        ]);
    });

    it('says one job in singular, and raises nothing when all is well', () => {
        expect(activeAlerts([], 1)[0]?.title).toBe('1 trabajo falló hoy');
        expect(activeAlerts([ok('Colas (Horizon)')], 0)).toEqual([]);
    });

    it('marks a stopped service as destructive', () => {
        expect(
            activeAlerts(
                [
                    {
                        name: 'Programador de tareas',
                        detail: 'No hay latido guardado.',
                        state: { variant: 'destructive', label: 'Sin latido' },
                    },
                ],
                0,
            )[0]?.tone,
        ).toBe('destructive');
    });
});
