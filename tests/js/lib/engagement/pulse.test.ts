import { describe, expect, it } from 'vite-plus/test';

import {
    cadenceText,
    leavesIn,
    modeHint,
    nextRunTitle,
    scheduleBadge,
    upcomingText,
} from '@/lib/engagement/pulse';
import type { PulseSchedule } from '@/types';

const schedule = (overrides: Partial<PulseSchedule>): PulseSchedule => ({
    frequency: 'monthly',
    frequencyLabel: 'Mensual',
    dayOfMonth: 15,
    openDays: 7,
    active: true,
    nextRunOn: '2026-12-15',
    daysUntil: 34,
    nextMode: null,
    upcoming: { mode: 'tour', modeLabel: 'Recorrido', items: 10 },
    ...overrides,
});

describe('modeHint', () => {
    it('says what each mode looks at', () => {
        expect(modeHint('follow_up')).toContain('prioridades');
        expect(modeHint('tour')).toContain('cada tema');
        expect(modeHint('manager')).toContain('jefe');
    });

    it('says nothing of a mode it does not know', () => {
        expect(modeHint('always_open')).toBe('');
    });
});

describe('scheduleBadge', () => {
    it('says how often and which day, or that it is paused', () => {
        expect(scheduleBadge(schedule({}))).toBe('Mensual · día 15');
        expect(scheduleBadge(schedule({ active: false }))).toBe('En pausa');
    });
});

describe('leavesIn', () => {
    it('says how soon the next pulse goes out', () => {
        expect(leavesIn(0)).toBe('Sale hoy');
        expect(leavesIn(1)).toBe('Sale mañana');
        expect(leavesIn(4)).toBe('Sale en 4 días');
    });
});

describe('nextRunTitle', () => {
    it('says the day the next pulse goes out, or that it is paused', () => {
        expect(nextRunTitle(schedule({}))).toBe(
            'Sale el 15 de diciembre de 2026',
        );
        expect(nextRunTitle(schedule({ active: false }))).toBe('En pausa');
    });
});

describe('cadenceText', () => {
    it('says the rhythm of the schedule in a sentence', () => {
        expect(cadenceText(schedule({}))).toBe(
            'Mensual, el día 15 de cada mes.',
        );
        expect(
            cadenceText(
                schedule({
                    frequency: 'quarterly',
                    frequencyLabel: 'Trimestral',
                }),
            ),
        ).toBe('Trimestral, el día 15 cada tres meses.');
        expect(cadenceText(schedule({ active: false }))).toBe(
            'No sale solo hasta que lo reanudes.',
        );
    });
});

describe('upcomingText', () => {
    it('names the mode of the next pulse and how many questions it has', () => {
        expect(upcomingText(schedule({}))).toBe('Recorrido · 10 preguntas');
        expect(
            upcomingText(
                schedule({
                    upcoming: {
                        mode: 'tour',
                        modeLabel: 'Recorrido',
                        items: 1,
                    },
                }),
            ),
        ).toBe('Recorrido · 1 pregunta');
    });
});
