import {
    type Remembered,
    rememberedOf,
    type Session,
    sessionOf,
} from '@/lib/assistant/invitation-rules';

/**
 * Where the browser keeps the invitations of the bubble for one person in one company: what they declined in
 * `localStorage` (it outlives the tab), how many showed and whether Humi opened in `sessionStorage` (the tab). Nothing goes
 * to the server. A browser that refuses its storage (a private window, blocked site data) starts fresh each time.
 */
export function invitationKey(userId: number, slug: string): string {
    return `humi.suggestions.${userId}.${slug}`;
}

function load(storage: () => Storage, key: string): unknown {
    try {
        const raw = storage().getItem(key);

        return raw === null ? null : JSON.parse(raw);
    } catch {
        return null;
    }
}

function save(storage: () => Storage, key: string, value: unknown): void {
    try {
        storage().setItem(key, JSON.stringify(value));
    } catch {
        // Without storage the invitations are only as calm as this page: nothing to do.
    }
}

const local = () => localStorage;
const tab = () => sessionStorage;

export function loadRemembered(key: string): Remembered {
    return rememberedOf(load(local, key));
}

export function saveRemembered(key: string, value: Remembered): void {
    save(local, key, value);
}

export function loadSession(key: string): Session {
    return sessionOf(load(tab, key));
}

export function saveSession(key: string, value: Session): void {
    save(tab, key, value);
}
