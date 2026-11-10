import { describe, expect, it } from 'vite-plus/test';

import { modeHint, scheduleText } from '@/lib/engagement/pulse';
import type { PulseSchedule } from '@/types';

const schedule = (overrides: Partial<PulseSchedule>): PulseSchedule => ({
    frequency: 'monthly',
    frequencyLabel: 'Mensual',
    dayOfMonth: 15,
    openDays: 7,
    active: true,
    nextRunOn: '2026-12-15',
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

describe('scheduleText', () => {
    it('says when the next pulse goes out, in which mode and how long it is', () => {
        expect(scheduleText(schedule({}))).toBe(
            'El siguiente pulso sale el 15 de diciembre de 2026 · Recorrido · 10 preguntas',
        );
    });

    it('says a paused schedule does not go out by itself', () => {
        expect(scheduleText(schedule({ active: false }))).toBe(
            'En pausa: no sale solo hasta que lo reanudes.',
        );
    });
});
