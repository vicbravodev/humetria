import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { draftAfterOpen, OPEN_HUMI, openHumi } from '@/lib/assistant/open-humi';

/** What a listener of `window` receives when Humi is asked to open. */
let seen: unknown[];

beforeEach(() => {
    const target = new EventTarget();
    seen = [];
    target.addEventListener(OPEN_HUMI, (event) => {
        seen.push(event instanceof CustomEvent ? event.detail : event);
    });
    vi.stubGlobal('window', target);
});

afterEach(() => vi.unstubAllGlobals());

describe('openHumi', () => {
    it('opens Humi with a draft', () => {
        openHumi('¿Qué sigue?');

        expect(seen).toEqual([{ draft: '¿Qué sigue?' }]);
    });

    it('opens Humi without a draft', () => {
        openHumi();

        expect(seen).toEqual([{ draft: null }]);
    });
});

describe('draftAfterOpen', () => {
    it('takes the new draft', () =>
        expect(draftAfterOpen('old', 'new')).toBe('new'));

    it('keeps the draft that is open when asked to open with none, so the box is not wiped', () =>
        expect(draftAfterOpen('old', null)).toBe('old'));

    it('stays empty when there was none and none comes', () =>
        expect(draftAfterOpen(null, null)).toBeNull());
});
