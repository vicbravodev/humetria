import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { OPEN_HUMI, openHumi } from '@/lib/assistant/open-humi';

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
