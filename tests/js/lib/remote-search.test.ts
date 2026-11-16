import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';

import type { Option, SearchState } from '@/lib/option-search';
import { createRemoteSearch } from '@/lib/remote-search';

const option = (label: string): Option => ({ value: label, label });

let states: SearchState[] = [];
const last = () => states.at(-1);

/** A remote search that waits 300 ms and needs 2 letters, recording every state it reports. */
const searcher = (
    load: (query: string, signal: AbortSignal) => Promise<Option[]>,
) =>
    createRemoteSearch({
        load,
        delay: 300,
        minLength: 2,
        onChange: (state: SearchState) => states.push(state),
    });

beforeEach(() => {
    vi.useFakeTimers();
    states = [];
});

afterEach(() => {
    vi.useRealTimers();
});

describe('createRemoteSearch while typing', () => {
    it('waits until typing pauses, and asks once for the last text', async () => {
        const load = vi.fn(async (query: string) => [option(query)]);
        const search = searcher(load);

        search.search('qu');
        await vi.advanceTimersByTimeAsync(200);
        search.search('que');
        await vi.advanceTimersByTimeAsync(299);

        expect(load).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1);

        expect(load).toHaveBeenCalledTimes(1);
        expect(load.mock.calls[0]?.[0]).toBe('que');
        expect(last()).toEqual({ status: 'done', options: [option('que')] });
    });

    it('does not ask below the minimum length, and goes back to idle', async () => {
        const load = vi.fn(async () => [option('x')]);
        const search = searcher(load);

        search.search(' q ');
        await vi.advanceTimersByTimeAsync(1000);

        expect(load).not.toHaveBeenCalled();
        expect(last()).toEqual({ status: 'idle', options: [] });
    });

    it('keeps the previous results on screen while the next ones load', async () => {
        let resolve: (options: Option[]) => void = () => {};
        const search = searcher((query) =>
            query === 'qu'
                ? Promise.resolve([option('Querétaro')])
                : new Promise((done) => {
                      resolve = done;
                  }),
        );

        search.search('qu');
        await vi.advanceTimersByTimeAsync(300);
        search.search('que');
        await vi.advanceTimersByTimeAsync(300);

        expect(last()).toEqual({
            status: 'loading',
            options: [option('Querétaro')],
        });

        resolve([option('Querétaro Centro')]);
        await vi.advanceTimersByTimeAsync(0);

        expect(last()).toEqual({
            status: 'done',
            options: [option('Querétaro Centro')],
        });
    });

    it('cancels the request in flight when the text changes, and ignores its answer', async () => {
        const signals: AbortSignal[] = [];
        const answers: ((options: Option[]) => void)[] = [];
        const search = searcher(
            (_query, signal) =>
                new Promise((done) => {
                    signals.push(signal);
                    answers.push(done);
                }),
        );

        search.search('qu');
        await vi.advanceTimersByTimeAsync(300);
        search.search('mo');
        await vi.advanceTimersByTimeAsync(300);

        expect(signals[0]?.aborted).toBe(true);

        answers[1]?.([option('Morelos')]);
        answers[0]?.([option('Querétaro')]);
        await vi.advanceTimersByTimeAsync(0);

        expect(last()).toEqual({
            status: 'done',
            options: [option('Morelos')],
        });
    });
});

describe('createRemoteSearch memory and cancellation', () => {
    it('answers a text it already asked for from memory, at once, ignoring case and accents', async () => {
        const load = vi.fn(async () => [option('Querétaro')]);
        const search = searcher(load);

        search.search('quere');
        await vi.advanceTimersByTimeAsync(300);
        search.search('quer');
        await vi.advanceTimersByTimeAsync(300);
        // No wait: the answer for "quere" is remembered.
        search.search('QUERÉ');

        expect(load).toHaveBeenCalledTimes(2);
        expect(last()).toEqual({
            status: 'done',
            options: [option('Querétaro')],
        });
    });

    it('reports a failed request, and does not remember it', async () => {
        const load = vi
            .fn<(query: string) => Promise<Option[]>>()
            .mockRejectedValueOnce(new Error('offline'))
            .mockResolvedValueOnce([option('Querétaro')]);
        const search = searcher(load);

        search.search('qu');
        await vi.advanceTimersByTimeAsync(300);

        expect(last()).toEqual({ status: 'error', options: [] });

        search.search('qu');
        await vi.advanceTimersByTimeAsync(300);

        expect(load).toHaveBeenCalledTimes(2);
        expect(last()).toEqual({
            status: 'done',
            options: [option('Querétaro')],
        });
    });

    it('stops the pending wait and the request in flight on cancel', async () => {
        const signals: AbortSignal[] = [];
        const load = vi.fn(
            (_query: string, signal: AbortSignal) =>
                new Promise<Option[]>(() => {
                    signals.push(signal);
                }),
        );
        const search = searcher(load);

        search.search('qu');
        await vi.advanceTimersByTimeAsync(300);
        search.search('que');
        search.cancel();
        await vi.advanceTimersByTimeAsync(1000);

        expect(load).toHaveBeenCalledTimes(1);
        expect(signals[0]?.aborted).toBe(true);
    });

    it('forgets the oldest texts once it remembers too many', async () => {
        const load = vi.fn(async (query: string) => [option(query)]);
        const search = createRemoteSearch({
            load,
            delay: 0,
            minLength: 1,
            cacheSize: 2,
            onChange: () => {},
        });

        for (const query of ['a', 'b', 'c', 'a']) {
            search.search(query);
            await vi.advanceTimersByTimeAsync(0);
        }

        expect(load.mock.calls.map(([query]) => query)).toEqual([
            'a',
            'b',
            'c',
            'a',
        ]);
    });
});
