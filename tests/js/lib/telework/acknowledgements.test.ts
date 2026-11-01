import { describe, expect, it } from 'vite-plus/test';

import {
    ackQuery,
    liveAll,
    percent,
    progressText,
} from '@/lib/telework/acknowledgements';

describe('percent', () => {
    it('rounds the share who confirmed', () => {
        expect(percent({ acknowledged: 1, expected: 3 })).toBe(33);
        expect(percent({ acknowledged: 2, expected: 3 })).toBe(67);
        expect(percent({ acknowledged: 3, expected: 3 })).toBe(100);
    });

    it('is zero when nobody is expected', () => {
        expect(percent({ acknowledged: 0, expected: 0 })).toBe(0);
    });
});

describe('progressText', () => {
    it('writes the count and the percent', () => {
        expect(progressText({ acknowledged: 12, expected: 40 })).toBe(
            '12 de 40 (30 %)',
        );
    });
});

describe('ackQuery', () => {
    it('leaves the default view out', () => {
        expect(ackQuery({ status: 'all', q: '' })).toEqual({});
        expect(ackQuery({ status: 'all', q: '   ' })).toEqual({});
    });

    it('keeps the status and the trimmed search', () => {
        expect(ackQuery({ status: 'pending', q: ' ana ' })).toEqual({
            status: 'pending',
            q: 'ana',
        });
    });
});

describe('liveAll', () => {
    const all = { q: 'ana', count: 4 };

    it('keeps the choice while the pending view shows the same search', () => {
        expect(liveAll(all, { status: 'pending', q: 'ana' })).toBe(all);
    });

    it('drops it when the search or the view changes, or when there is none', () => {
        expect(liveAll(all, { status: 'pending', q: 'ana r' })).toBeNull();
        expect(liveAll(all, { status: 'all', q: 'ana' })).toBeNull();
        expect(liveAll(all, { status: 'acknowledged', q: 'ana' })).toBeNull();
        expect(liveAll(null, { status: 'pending', q: 'ana' })).toBeNull();
    });
});
