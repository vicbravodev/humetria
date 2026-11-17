import { afterEach, describe, expect, it, vi } from 'vite-plus/test';

import { copiesOf, scheduleCopies } from '@/lib/attendance/schedule-copy';

const ana = {
    value: 'ana',
    label: 'Ana López',
    schedule: {
        shiftType: 'day',
        workMode: 'onsite',
        minutes: { 1: 480, 2: 480, 3: 480, 4: 480, 5: 480, 6: 0, 7: 0 },
        starts: { 1: 540, 2: 540, 3: 540, 4: 540, 5: 540, 6: null, 7: null },
    },
};

describe('the people whose schedule can be copied', () => {
    it('are each an option with their schedule', () => {
        expect(copiesOf([ana])).toEqual([
            {
                option: { value: 'ana', label: 'Ana López' },
                schedule: ana.schedule,
            },
        ]);
    });

    it('take a schedule with no start times', () => {
        expect(
            copiesOf([{ ...ana, schedule: { ...ana.schedule, starts: null } }]),
        ).toHaveLength(1);
    });

    it('leave out what does not have the shape of one', () => {
        expect(copiesOf({ value: 'ana' })).toEqual([]);
        expect(
            copiesOf([
                { ...ana, schedule: { ...ana.schedule, shiftType: 'evening' } },
                { ...ana, schedule: { ...ana.schedule, minutes: { 1: '8' } } },
                { ...ana, schedule: { ...ana.schedule, starts: { 1: 'x' } } },
                { ...ana, schedule: null },
                { ...ana, value: 1 },
            ]),
        ).toEqual([]);
    });
});

describe('finding them on the server', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('asks for what was typed and keeps the schedule of each one found', async () => {
        const fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve([ana]),
        });
        vi.stubGlobal('fetch', fetch);
        const copies = scheduleCopies(
            '/admin/acme/attendance/schedules/options',
        );
        const signal = new AbortController().signal;

        expect(copies.scheduleOf('ana')).toBeNull();
        await expect(copies.source.load('ana l', signal)).resolves.toEqual([
            { value: 'ana', label: 'Ana López' },
        ]);
        expect(fetch).toHaveBeenCalledWith(
            '/admin/acme/attendance/schedules/options?q=ana+l',
            { headers: { Accept: 'application/json' }, signal },
        );
        expect(copies.scheduleOf('ana')).toEqual(ana.schedule);
    });

    it('fails when the server does', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: false,
                json: () => Promise.resolve([]),
            }),
        );

        await expect(
            scheduleCopies('/options').source.load(
                '',
                new AbortController().signal,
            ),
        ).rejects.toThrow('Options request failed: /options');
    });
});
