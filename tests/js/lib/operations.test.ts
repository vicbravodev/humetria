import { describe, expect, it } from 'vite-plus/test';

import {
    count,
    deliveredShare,
    services,
    since,
    trafficOf,
    troubleLine,
    troubled,
} from '@/lib/operations';
import type { SystemHealthData } from '@/types';

const now = new Date('2026-11-25T18:00:00Z');

const healthy: SystemHealthData = {
    queue: {
        status: 'running',
        waiting: 3,
        queues: [
            { name: 'default', length: 3, wait: 4 },
            { name: 'whatsapp', length: 0, wait: 9 },
        ],
    },
    failedJobs: { today: 0, week: [] },
    heartbeat: { lastAt: '2026-11-25T17:58:00Z', late: false },
    webhooks: { meta: '2026-11-25T17:59:30Z', resend: '2026-11-25T15:00:00Z' },
    whatsApp: { enabled: true, paused: false, pauseReason: null },
    today: [],
};

const states = (health: SystemHealthData) =>
    services(health, now).map(({ name, detail, state }) => [
        name,
        detail,
        state.label,
    ]);

describe('since', () => {
    it('says how long ago, in minutes, hours or days', () => {
        expect(since('2026-11-25T17:59:40Z', now)).toBe(
            'hace menos de un minuto',
        );
        expect(since('2026-11-25T17:57:00Z', now)).toBe('hace 3 min');
        expect(since('2026-11-25T16:00:00Z', now)).toBe('hace 2 h');
        expect(since('2026-11-24T17:00:00Z', now)).toBe('hace 1 día');
        expect(since('2026-11-21T18:00:00Z', now)).toBe('hace 4 días');
    });

    it('never says a time in the future is ahead', () => {
        expect(since('2026-11-25T18:05:00Z', now)).toBe(
            'hace menos de un minuto',
        );
    });
});

describe('services', () => {
    it('lists the queues, the scheduler and both webhooks when all is well', () => {
        expect(states(healthy)).toEqual([
            ['Colas (Horizon)', '3 esperando · espera máxima 9 s', 'Operativo'],
            [
                'Programador de tareas',
                'Late cada 5 min · último hace 2 min',
                'Operativo',
            ],
            ['Correo (Resend)', 'Último evento hace 3 h', 'Recibe eventos'],
            [
                'WhatsApp (Meta)',
                'Último evento hace menos de un minuto',
                'Recibe eventos',
            ],
        ]);
        expect(troubled(services(healthy, now))).toBe(0);
    });

    it('says when Horizon is paused, stopped or Redis does not answer', () => {
        const queue = (status: SystemHealthData['queue']['status']) =>
            services(
                {
                    ...healthy,
                    queue: { status, waiting: 0, queues: [] },
                },
                now,
            )[0];

        expect(queue('paused')?.state.label).toBe('En pausa');
        expect(queue('paused')?.detail).toBe('0 esperando · espera máxima 0 s');
        expect(queue('inactive')?.state.label).toBe('Detenido');
        expect(queue('unreachable')?.state).toEqual({
            variant: 'destructive',
            label: 'No responde',
        });
        expect(queue('unreachable')?.detail).toBe(
            'Redis no contestó: no se sabe cuántos trabajos esperan.',
        );
    });

    it('says when the scheduler stopped beating or never did', () => {
        const late = services(
            { ...healthy, heartbeat: { lastAt: null, late: true } },
            now,
        );

        expect(late[1]?.detail).toBe('No hay latido guardado.');
        expect(late[1]?.state.label).toBe('Sin latido');
        expect(troubled(late)).toBe(1);
    });

    it('says when a webhook never received an event', () => {
        const quiet = services(
            { ...healthy, webhooks: { meta: null, resend: null } },
            now,
        );

        expect(quiet[2]?.state.label).toBe('Sin eventos');
        expect(quiet[3]?.detail).toBe('El webhook no ha recibido eventos.');
        expect(troubled(quiet)).toBe(0);
    });

    it('says whether WhatsApp is off or paused, and why', () => {
        const whatsApp = (state: SystemHealthData['whatsApp']) =>
            services({ ...healthy, whatsApp: state }, now)[3];

        expect(
            whatsApp({ enabled: false, paused: false, pauseReason: null })
                ?.state.label,
        ).toBe('Apagado');
        expect(
            whatsApp({ enabled: true, paused: true, pauseReason: null })
                ?.detail,
        ).toBe('No se envía: la pausó quien opera la plataforma.');
        expect(
            whatsApp({
                enabled: true,
                paused: true,
                pauseReason: 'failure_rate',
            })?.detail,
        ).toBe('No se envía: se pausó solo: demasiados rechazos.');
        expect(
            whatsApp({ enabled: true, paused: true, pauseReason: 'other' })
                ?.detail,
        ).toBe('No se envía: other.');
    });
});

describe('figures', () => {
    const today = [
        {
            channel: 'mail' as const,
            total: 3,
            delivered: 2,
            read: 0,
            rejected: 1,
        },
    ];

    it('finds the traffic of a channel and its delivered share', () => {
        expect(deliveredShare(trafficOf(today, 'mail'))).toBe('66.7 %');
        expect(deliveredShare(trafficOf(today, 'whatsapp'))).toBe('—');
        expect(
            deliveredShare({
                channel: 'mail',
                total: 0,
                delivered: 0,
                read: 0,
                rejected: 0,
            }),
        ).toBe('—');
    });

    it('says how many services have trouble', () => {
        expect(troubleLine(0)).toBe('Todo operativo');
        expect(troubleLine(1)).toBe('1 servicio con problemas');
        expect(troubleLine(2)).toBe('2 servicios con problemas');
    });

    it('writes a count with thousands', () => {
        expect(count(2140)).toBe('2,140');
    });
});
