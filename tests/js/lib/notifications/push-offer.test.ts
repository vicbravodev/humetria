import { describe, expect, it } from 'vitest';
import {
    DISMISS_DAYS,
    dismissOffer,
    dismissedAt,
    markedBefore,
    publicKeyBytes,
    rememberMark,
    shouldOfferPush,
    subscriptionBody,
} from '@/lib/notifications/push';
import type { PushOfferState } from '@/lib/notifications/push';
import { KEY, KEY_BYTES } from '../../support/push-phone';

const DAY = 86_400_000;
const NOW = Date.UTC(2026, 10, 24, 15);

const OPEN: PushOfferState = {
    supported: true,
    permission: 'default',
    standalone: true,
    ios: false,
    markedOnce: true,
    dismissedAt: null,
    now: NOW,
};

describe('shouldOfferPush', () => {
    it('offers the notices to someone who marked once and has not been asked', () => {
        expect(shouldOfferPush(OPEN)).toBe(true);
    });

    it.each([
        ['the browser cannot push', { supported: false }],
        [
            'the person allowed them already (the app subscribes by itself)',
            { permission: 'granted' as const },
        ],
        [
            'the person refused them in the system',
            { permission: 'denied' as const },
        ],
        [
            'an iPhone without the app installed',
            { ios: true, standalone: false },
        ],
        ['nobody has marked yet', { markedOnce: false }],
    ])('does not offer them when %s', (_case, change) => {
        expect(shouldOfferPush({ ...OPEN, ...change })).toBe(false);
    });

    it('offers them on an iPhone with the app installed', () => {
        expect(shouldOfferPush({ ...OPEN, ios: true, standalone: true })).toBe(
            true,
        );
    });

    it('offers them in a browser tab outside an iPhone, installed or not', () => {
        expect(shouldOfferPush({ ...OPEN, standalone: false })).toBe(true);
    });

    it('stays quiet for 14 days after «Ahora no»: 13 days no, 14 days yes', () => {
        expect(DISMISS_DAYS).toBe(14);
        expect(shouldOfferPush({ ...OPEN, dismissedAt: NOW - 13 * DAY })).toBe(
            false,
        );
        expect(shouldOfferPush({ ...OPEN, dismissedAt: NOW - 14 * DAY })).toBe(
            true,
        );
        expect(
            shouldOfferPush({ ...OPEN, dismissedAt: NOW - 14 * DAY + 1 }),
        ).toBe(false);
    });
});

describe('subscriptionBody', () => {
    const json = {
        endpoint: 'https://fcm.googleapis.com/fcm/send/abc',
        keys: { p256dh: 'p', auth: 'a' },
    };

    it('keeps the address and the two keys, and drops anything else', () => {
        expect(subscriptionBody({ ...json, expirationTime: null })).toEqual(
            json,
        );
    });

    it.each([
        [
            'an address that is not https',
            { ...json, endpoint: 'http://fcm.googleapis.com/x' },
        ],
        ['no address', { keys: json.keys }],
        ['no keys', { endpoint: json.endpoint }],
        ['no p256dh', { endpoint: json.endpoint, keys: { auth: 'a' } }],
        ['no auth', { endpoint: json.endpoint, keys: { p256dh: 'p' } }],
        [
            'an empty key',
            { endpoint: json.endpoint, keys: { p256dh: '', auth: 'a' } },
        ],
    ])('refuses %s', (_case, bad) => {
        expect(subscriptionBody(bad)).toBeNull();
    });
});

describe('publicKeyBytes', () => {
    it('decodes the base64url key the server gives into the bytes the browser subscribes with', () => {
        expect(Array.from(publicKeyBytes(KEY))).toEqual(Array.from(KEY_BYTES));
    });
});

describe('what the browser remembers', () => {
    function storage() {
        const kept = new Map<string, string>();

        return {
            getItem: (key: string) => kept.get(key) ?? null,
            setItem: (key: string, value: string) => void kept.set(key, value),
        };
    }

    const refusing = {
        getItem: () => {
            throw new Error('blocked');
        },
        setItem: () => {
            throw new Error('blocked');
        },
    };

    it('remembers that a mark was made, and nothing else of it', () => {
        const store = storage();

        expect(markedBefore(store)).toBe(false);
        rememberMark(store);

        expect(markedBefore(store)).toBe(true);
    });

    it('remembers when «Ahora no» was tapped', () => {
        const store = storage();

        expect(dismissedAt(store)).toBeNull();
        dismissOffer(store, NOW);

        expect(dismissedAt(store)).toBe(NOW);
    });

    it('reads nothing from a date that is not one', () => {
        const store = storage();
        store.setItem('humetria-push-dismissed', 'ayer');

        expect(dismissedAt(store)).toBeNull();
    });

    it('goes on when the storage is blocked: it remembers nothing and does not fail', () => {
        rememberMark(refusing);
        dismissOffer(refusing, NOW);

        expect(markedBefore(refusing)).toBe(false);
        expect(dismissedAt(refusing)).toBeNull();
    });
});
