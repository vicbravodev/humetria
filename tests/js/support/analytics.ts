import { vi } from 'vitest';

export const TOKEN = 'phc_AbCdEfGhIjKlMnOpQrStUvWxYz0123456789ABCDEFG';
export const HOST = 'https://us.i.posthog.com';

export function memoryStorage(initial: Record<string, string> = {}) {
    const values = new Map(Object.entries(initial));

    return {
        getItem: (key: string) => values.get(key) ?? null,
        setItem: (key: string, value: string) => {
            values.set(key, value);
        },
        values,
    };
}

/** The document of a landing: the JSON block of the analytics configuration holds this text (none: no block). */
export function documentWith(text: string | null) {
    return {
        getElementById: (id: string) =>
            id === 'analytics-config' && text !== null
                ? { textContent: text }
                : null,
    };
}

export const LANDING_BLOCK = JSON.stringify({
    token: TOKEN,
    host: HOST,
    page_location: 'http://localhost/',
});

export function engine() {
    return { init: vi.fn(), capture: vi.fn(), captureException: vi.fn() };
}
