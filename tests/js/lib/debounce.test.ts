import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';

import { debounce } from '@/lib/debounce';

beforeEach(() => {
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('debounce', () => {
    it('calls once, with the last arguments, after the calls stop for the delay', () => {
        const run = vi.fn();
        const debounced = debounce(run, 300);

        debounced.call('a');
        vi.advanceTimersByTime(200);
        debounced.call('ab');
        vi.advanceTimersByTime(299);

        expect(run).not.toHaveBeenCalled();

        vi.advanceTimersByTime(1);

        expect(run).toHaveBeenCalledOnce();
        expect(run).toHaveBeenCalledWith('ab');
    });

    it('drops the pending call on cancel', () => {
        const run = vi.fn();
        const debounced = debounce(run, 300);

        debounced.call('a');
        debounced.cancel();
        vi.advanceTimersByTime(1000);

        expect(run).not.toHaveBeenCalled();
    });
});
