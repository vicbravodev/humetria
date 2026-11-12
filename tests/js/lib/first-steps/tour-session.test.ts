import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { rememberTour, takeTour } from '@/lib/first-steps/tour-session';

const KEY = 'humetria:first-steps-tour';

function stubStorage(overrides: Partial<Storage> = {}): Map<string, string> {
    const data = new Map<string, string>();
    const storage = {
        getItem: (key: string) => data.get(key) ?? null,
        setItem: (key: string, value: string) => void data.set(key, value),
        removeItem: (key: string) => void data.delete(key),
        ...overrides,
    };
    vi.stubGlobal('window', { sessionStorage: storage });

    return data;
}

const blocked = () => {
    throw new Error('blocked');
};

describe('tour session', () => {
    beforeEach(() => void stubStorage());
    afterEach(() => vi.unstubAllGlobals());

    it('remembers a tour once', () => {
        rememberTour('people');
        expect(takeTour()).toBe('people');
        expect(takeTour()).toBeNull();
    });

    it('gives no tour when there is nothing stored', () =>
        expect(takeTour()).toBeNull());

    it('gives no tour for something that is not a step key', () => {
        stubStorage().set(KEY, 'whatever');
        expect(takeTour()).toBeNull();
    });

    it('gives no tour when sessionStorage throws', () => {
        stubStorage({ getItem: blocked });
        expect(takeTour()).toBeNull();
    });

    it('does not break when sessionStorage throws on write', () => {
        stubStorage({ setItem: blocked });
        expect(() => rememberTour('team')).not.toThrow();
    });
});
