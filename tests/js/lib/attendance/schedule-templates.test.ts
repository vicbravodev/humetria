import { describe, expect, it } from 'vite-plus/test';

import { weekTotal } from '@/lib/attendance/schedule';
import {
    matchTemplate,
    scheduleTemplates,
    templateSummary,
} from '@/lib/attendance/schedule-templates';

const limits = (minutes: number) => ({
    year: 2026,
    minutes,
    overtimeMinutes: 540,
    dailyMinutes: { day: 480, night: 420, mixed: 450 },
});

const week = (...days: number[]) =>
    Object.fromEntries(days.map((minutes, at) => [String(at + 1), minutes]));

describe('the templates to start a schedule from', () => {
    it('are the common weeks, each saying what it fills and its total', () => {
        expect(
            scheduleTemplates(limits(2880)).map((template) => [
                template.title,
                templateSummary(template),
            ]),
        ).toEqual([
            ['Lunes a viernes', 'L–V 8 h · 40 h'],
            ['Lunes a viernes, 9 h', 'L–V 9 h · 45 h'],
            ['Con sábado medio', 'L–V 8 h · S 5 h · 45 h'],
            ['Lunes a sábado', 'L–S 8 h · 48 h'],
            ['Nocturno', 'L–V 7 h · 35 h'],
        ]);
    });

    it('carry their shift, and no start time', () => {
        const templates = scheduleTemplates(limits(2880));

        expect(templates.some((template) => 'start' in template)).toBe(false);
        expect(templates.map((template) => template.shift)).toEqual([
            'day',
            'day',
            'day',
            'day',
            'night',
        ]);
    });

    it("never start above the year's maximum: their days shrink to the quarter hour that fits", () => {
        const in2027 = scheduleTemplates(limits(2760));

        expect(in2027.map(templateSummary)).toEqual([
            'L–V 8 h · 40 h',
            'L–V 9 h · 45 h',
            'L–V 8 h · S 5 h · 45 h',
            'L–S 7 h 30 min · 45 h',
            'L–V 7 h · 35 h',
        ]);

        for (const maximum of [2880, 2760, 2640, 2520, 2400]) {
            for (const template of scheduleTemplates(limits(maximum))) {
                expect(weekTotal(template.minutes)).toBeLessThanOrEqual(
                    maximum,
                );
            }
        }

        expect(scheduleTemplates(limits(2400)).map(templateSummary)).toEqual([
            'L–V 8 h · 40 h',
            'L–V 8 h · 40 h',
            'L–V 8 h · 40 h',
            'L–S 6 h 30 min · 39 h',
            'L–V 7 h · 35 h',
        ]);
    });

    it('take the night shift at its daily limit', () => {
        const night = scheduleTemplates({
            ...limits(2880),
            dailyMinutes: { day: 480, night: 360, mixed: 450 },
        }).find((template) => template.id === 'night');

        expect(night?.minutes).toEqual(week(360, 360, 360, 360, 360, 0, 0));
    });
});

describe('the template a week is', () => {
    const templates = scheduleTemplates(limits(2880));

    it('is the one with the same minutes every day', () => {
        expect(
            matchTemplate(templates, week(480, 480, 480, 480, 480, 300, 0)),
        ).toBe('mon_fri_half_sat');
        expect(
            matchTemplate(templates, week(480, 480, 480, 480, 480, 0, 0)),
        ).toBe('mon_fri');
    });

    it('is none when a day differs', () => {
        expect(
            matchTemplate(templates, week(480, 480, 480, 480, 420, 0, 0)),
        ).toBeNull();
        expect(
            matchTemplate(templates, week(480, 480, 480, 480, 480, 0, 60)),
        ).toBeNull();
    });
});
