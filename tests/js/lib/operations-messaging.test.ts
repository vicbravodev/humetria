import { describe, expect, it } from 'vite-plus/test';

import {
    categoryLabel,
    connectionFacts,
    connectionState,
    funnel,
    rejected,
    templateLabel,
} from '@/lib/operations-messaging';
import type { MessagingConnection } from '@/types';

const now = new Date('2026-11-25T18:00:00Z');

const on: MessagingConnection = {
    enabled: true,
    paused: false,
    pauseReason: null,
    pausedSince: null,
    lastEventAt: '2026-11-25T17:58:00Z',
};

describe('the connection with Meta', () => {
    it('says whether WhatsApp is off, paused or sending', () => {
        expect(connectionState(on).label).toBe('Enviando');
        expect(connectionState({ ...on, enabled: false }).label).toBe(
            'Apagado',
        );
        expect(connectionState({ ...on, paused: true })).toEqual({
            variant: 'warning',
            label: 'En pausa',
        });
    });

    it('gives only the last event while it sends', () => {
        expect(connectionFacts(on, now)).toEqual([
            { label: 'Último evento de Meta', value: 'hace 2 min' },
        ]);
        expect(connectionFacts({ ...on, lastEventAt: null }, now)).toEqual([
            { label: 'Último evento de Meta', value: 'Ninguno' },
        ]);
    });

    it('says why and since when it is paused', () => {
        const paused = {
            ...on,
            paused: true,
            pauseReason: 'failure_rate',
            pausedSince: '2026-11-25T16:00:00Z',
        };

        expect(connectionFacts(paused, now).slice(0, 2)).toEqual([
            { label: 'Motivo de la pausa', value: 'Demasiados rechazos' },
            { label: 'En pausa desde', value: 'hace 2 h' },
        ]);
        expect(
            connectionFacts({ ...paused, pauseReason: null }, now)[0]?.value,
        ).toBe('La pausó quien opera la plataforma');
        expect(
            connectionFacts({ ...paused, pauseReason: 'other' }, now)[0]?.value,
        ).toBe('other');
    });
});

describe('the traffic', () => {
    const whatsApp = {
        channel: 'whatsapp' as const,
        total: 4,
        delivered: 3,
        read: 2,
        rejected: 1,
    };

    it('steps from sent to read with each share of what was sent', () => {
        expect(funnel(whatsApp)).toEqual([
            { label: 'Enviados', value: '4', percent: 100 },
            { label: 'Entregados', value: '3', percent: 75 },
            { label: 'Leídos', value: '2', percent: 50 },
            { label: 'Rechazados', value: '1', percent: 25 },
        ]);
        expect(funnel(undefined).map(({ percent }) => percent)).toEqual([
            0, 0, 0, 0,
        ]);
    });

    it('adds up the rejected of every channel', () => {
        expect(
            rejected([whatsApp, { ...whatsApp, channel: 'mail', rejected: 2 }]),
        ).toBe(3);
    });

    it('names a template in Spanish with its version, or by its key when it has no name', () => {
        expect(templateLabel('survey_invite_v1')).toBe(
            'Invitación a la evaluación NOM-035 · v1',
        );
        expect(templateLabel('proof_code_v2')).toBe(
            'Clave de verificación · v2',
        );
        expect(templateLabel('brand_new_v1')).toBe('brand_new_v1');
        expect(templateLabel('unversioned')).toBe('unversioned');
    });

    it('names the category of a template', () => {
        expect(categoryLabel('UTILITY')).toBe('Utilidad');
        expect(categoryLabel('AUTHENTICATION')).toBe('Autenticación');
    });
});
