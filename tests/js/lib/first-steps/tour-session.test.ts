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
        rememberTour('people', 'acme');
        expect(takeTour('acme')).toBe('people');
        expect(takeTour('acme')).toBeNull();
    });

    it('gives no tour when there is nothing stored', () =>
        expect(takeTour('acme')).toBeNull());

    it('gives no tour for something that is not a step key', () => {
        stubStorage().set(KEY, 'acme:whatever');
        expect(takeTour('acme')).toBeNull();
    });

    it('gives no tour that was remembered for another company', () => {
        rememberTour('people', 'acme');
        expect(takeTour('globex')).toBeNull();
    });

    it('clears a tour of another company when it is looked at', () => {
        const data = stubStorage();
        rememberTour('people', 'acme');
        takeTour('globex');
        expect(data.has(KEY)).toBe(false);
    });

    it('gives no tour for a value without a company', () => {
        stubStorage().set(KEY, 'people');
        expect(takeTour('acme')).toBeNull();
    });

    it('keeps a slug with a colon apart from the key', () => {
        rememberTour('team', 'a:b');
        expect(takeTour('a:b')).toBe('team');
    });

    it('gives no tour when sessionStorage throws', () => {
        stubStorage({ getItem: blocked });
        expect(takeTour('acme')).toBeNull();
    });

    it('does not break when sessionStorage throws on write', () => {
        stubStorage({ setItem: blocked });
        expect(() => rememberTour('team', 'acme')).not.toThrow();
    });
});
