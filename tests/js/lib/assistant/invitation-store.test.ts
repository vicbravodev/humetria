import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { FRESH, FRESH_SESSION } from '@/lib/assistant/invitation-rules';
import {
    invitationKey,
    loadRemembered,
    loadSession,
    saveRemembered,
    saveSession,
} from '@/lib/assistant/invitation-store';

function memoryStorage(): Storage {
    const items = new Map<string, string>();

    return {
        get length() {
            return items.size;
        },
        clear: () => items.clear(),
        getItem: (key) => items.get(key) ?? null,
        key: () => null,
        removeItem: (key) => items.delete(key),
        setItem: (key, value) => items.set(key, value),
    };
}

function brokenStorage(): Storage {
    const fail = () => {
        throw new Error('SecurityError');
    };

    return {
        length: 0,
        clear: fail,
        getItem: fail,
        key: fail,
        removeItem: fail,
        setItem: fail,
    };
}

afterEach(() => vi.unstubAllGlobals());

describe('invitation store', () => {
    it('keeps what the person declined apart for each person and company', () => {
        expect(invitationKey(7, 'acme')).toBe('humi.suggestions.7.acme');
        expect(invitationKey(7, 'beta')).not.toBe(invitationKey(7, 'acme'));
        expect(invitationKey(8, 'acme')).not.toBe(invitationKey(7, 'acme'));
    });

    it('reads back what it kept: the declined in the browser, the count in the tab', () => {
        vi.stubGlobal('localStorage', memoryStorage());
        vi.stubGlobal('sessionStorage', memoryStorage());
        const key = invitationKey(7, 'acme');

        saveRemembered(key, { ...FRESH, off: true });
        saveSession(key, { shown: 1, opened: true });

        expect(loadRemembered(key)).toEqual({ ...FRESH, off: true });
        expect(loadSession(key)).toEqual({ shown: 1, opened: true });
        expect(loadRemembered(invitationKey(7, 'beta'))).toEqual(FRESH);
    });

    it('starts fresh and keeps working when the browser refuses its storage or kept something else', () => {
        vi.stubGlobal('localStorage', brokenStorage());
        vi.stubGlobal('sessionStorage', brokenStorage());
        const key = invitationKey(7, 'acme');

        expect(() => saveRemembered(key, FRESH)).not.toThrow();
        expect(() => saveSession(key, FRESH_SESSION)).not.toThrow();
        expect(loadRemembered(key)).toEqual(FRESH);
        expect(loadSession(key)).toEqual(FRESH_SESSION);

        vi.stubGlobal('localStorage', memoryStorage());
        localStorage.setItem(key, '{no es json');
        expect(loadRemembered(key)).toEqual(FRESH);
    });
});
