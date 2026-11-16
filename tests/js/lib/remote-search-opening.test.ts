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

beforeEach(() => {
    vi.useFakeTimers();
    states = [];
});

afterEach(() => {
    vi.useRealTimers();
});

describe('createRemoteSearch with a short list', () => {
    it('filters here once the first page was the whole list, without asking again', async () => {
        const load = vi.fn(async () => [option('Norte'), option('Sur')]);
        const search = createRemoteSearch({
            load,
            delay: 300,
            minLength: 0,
            onChange: (state: SearchState) => states.push(state),
        });

        search.search('');
        await vi.advanceTimersByTimeAsync(300);
        search.search('su');

        expect(last()).toEqual({ status: 'done', options: [option('Sur')] });
        expect(load).toHaveBeenCalledTimes(1);
    });

    it('keeps asking the server when the first page was full', async () => {
        const full = Array.from({ length: 20 }, (_, i) => option(`C${i}`));
        const load = vi.fn(async () => full);
        const search = createRemoteSearch({
            load,
            delay: 300,
            minLength: 0,
            onChange: (state: SearchState) => states.push(state),
        });

        search.search('');
        await vi.advanceTimersByTimeAsync(300);
        search.search('c1');
        await vi.advanceTimersByTimeAsync(300);

        expect(load).toHaveBeenCalledTimes(2);
    });
});

describe('createRemoteSearch while loading', () => {
    it('narrows the results still on screen to what was typed', async () => {
        const load = vi.fn(async (query: string) =>
            query === ''
                ? Array.from({ length: 20 }, (_, i) => option(`Centro ${i}`))
                : [option('Planta')],
        );
        const search = createRemoteSearch({
            load,
            delay: 300,
            minLength: 0,
            onChange: (state: SearchState) => states.push(state),
        });

        search.search('');
        await vi.advanceTimersByTimeAsync(300);
        search.search('planta');
        await vi.advanceTimersByTimeAsync(300);

        expect(states.find(({ status }) => status === 'loading')).toBeDefined();
        expect(
            states.filter(({ status }) => status === 'loading').at(-1)?.options,
        ).toEqual([]);
        expect(last()).toEqual({ status: 'done', options: [option('Planta')] });
    });
});

describe('createRemoteSearch on opening', () => {
    it('asks for the empty text at once, without the typing delay', async () => {
        const load = vi.fn(async () => [option('Norte')]);
        const search = createRemoteSearch({
            load,
            delay: 300,
            minLength: 0,
            onChange: (state: SearchState) => states.push(state),
        });

        search.search('');
        await vi.advanceTimersByTimeAsync(0);

        expect(load).toHaveBeenCalledTimes(1);
        expect(last()).toEqual({ status: 'done', options: [option('Norte')] });
    });
});

describe('createRemoteSearch typing while the first page loads', () => {
    it('lets the first page land and searches what was typed in it', async () => {
        let abortedBeforeAnswer: boolean | undefined;
        const load = vi.fn(async (_query: string, given: AbortSignal) => {
            await new Promise((resolve) => setTimeout(resolve, 100));
            abortedBeforeAnswer = given.aborted;

            return [option('Norte'), option('Sur')];
        });
        const search = createRemoteSearch({
            load,
            delay: 300,
            minLength: 0,
            onChange: (state: SearchState) => states.push(state),
        });

        search.search('');
        await vi.advanceTimersByTimeAsync(10);
        search.search('su');
        await vi.advanceTimersByTimeAsync(500);

        expect(abortedBeforeAnswer).toBe(false);
        expect(load).toHaveBeenCalledTimes(1);
        expect(last()).toEqual({ status: 'done', options: [option('Sur')] });
    });

    it('does not search again once stopped', async () => {
        const load = vi.fn(async () => [option('Norte')]);
        const search = createRemoteSearch({
            load,
            delay: 300,
            minLength: 0,
            onChange: (state: SearchState) => states.push(state),
        });

        search.search('');
        await vi.advanceTimersByTimeAsync(0);
        search.search('no');
        search.cancel();
        await vi.advanceTimersByTimeAsync(1000);

        expect(load).toHaveBeenCalledTimes(1);
    });
});
