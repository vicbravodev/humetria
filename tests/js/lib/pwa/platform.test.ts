import { describe, expect, it } from 'vitest';
import {
    guideKind,
    inAppBrowser,
    isInstalled,
    phoneOf,
    readPlatform,
    shouldAskPersistence,
} from '@/lib/pwa/platform';

const SAFARI_IPHONE =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';
const GMAIL_IPHONE =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) GSA/321.0.636 Mobile/15E148 Safari/604.1';
const OUTLOOK_IPHONE =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Outlook-iOS/4.2412.0';
const FACEBOOK_IPHONE =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBAV/450.0.0.0]';
const INSTAGRAM_ANDROID =
    'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/124.0.0.0 Mobile Safari/537.36 Instagram 330.0.0.0 Android';
const WEBVIEW_ANDROID =
    'Mozilla/5.0 (Linux; Android 14; Pixel 8; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/124.0.0.0 Mobile Safari/537.36';
const CHROME_ANDROID =
    'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36';
const MAC =
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15';

describe('isInstalled', () => {
    it('is installed when the display mode is standalone', () => {
        expect(isInstalled({ standaloneMedia: true, navigator: {} })).toBe(
            true,
        );
    });

    it('is installed when iOS says navigator.standalone', () => {
        expect(
            isInstalled({
                standaloneMedia: false,
                navigator: { standalone: true },
            }),
        ).toBe(true);
    });

    it('is not installed in a tab, nor when standalone is not exactly true', () => {
        expect(isInstalled({ standaloneMedia: false, navigator: {} })).toBe(
            false,
        );
        expect(
            isInstalled({
                standaloneMedia: false,
                navigator: { standalone: false },
            }),
        ).toBe(false);
        expect(
            isInstalled({
                standaloneMedia: false,
                navigator: { standalone: 'yes' },
            }),
        ).toBe(false);
    });
});

describe('inAppBrowser', () => {
    it.each([
        ['Gmail on iOS', GMAIL_IPHONE],
        ['Outlook', OUTLOOK_IPHONE],
        ['Facebook', FACEBOOK_IPHONE],
        ['Instagram', INSTAGRAM_ANDROID],
        ['an Android WebView', WEBVIEW_ANDROID],
    ])('knows %s is not a browser', (_name, agent) => {
        expect(inAppBrowser(agent)).toBe(true);
    });

    it.each([
        ['Safari on iPhone', SAFARI_IPHONE],
        ['Chrome on Android', CHROME_ANDROID],
        ['a desktop Mac', MAC],
    ])('lets %s through', (_name, agent) => {
        expect(inAppBrowser(agent)).toBe(false);
    });
});

describe('phoneOf', () => {
    it('tells an iPhone, an Android and anything else', () => {
        expect(phoneOf(SAFARI_IPHONE, 5)).toBe('iphone');
        expect(phoneOf(CHROME_ANDROID, 5)).toBe('android');
        expect(phoneOf(MAC, 0)).toBe('other');
        expect(phoneOf('curl/8.0', 0)).toBe('other');
    });

    it('takes an iPad as an iPhone, also when it says it is a Mac and has a touch screen', () => {
        expect(
            phoneOf('Mozilla/5.0 (iPad; CPU OS 17_4 like Mac OS X)', 5),
        ).toBe('iphone');
        expect(phoneOf(MAC, 5)).toBe('iphone');
        expect(phoneOf(MAC, 1)).toBe('other');
    });
});

describe('shouldAskPersistence', () => {
    it('asks only once installed and not yet persisted', () => {
        expect(shouldAskPersistence(true, false)).toBe(true);
        expect(shouldAskPersistence(true, true)).toBe(false);
        expect(shouldAskPersistence(false, false)).toBe(false);
        expect(shouldAskPersistence(false, true)).toBe(false);
    });
});

describe('readPlatform', () => {
    const win = (standalone: boolean, userAgent: string, touch = 5) => ({
        matchMedia: (query: string) => ({
            matches: standalone && query === '(display-mode: standalone)',
        }),
        navigator: { userAgent, maxTouchPoints: touch },
    });

    it('reads what the window says about itself', () => {
        expect(readPlatform(win(false, SAFARI_IPHONE))).toEqual({
            installed: false,
            inApp: false,
            phone: 'iphone',
        });
        expect(readPlatform(win(true, CHROME_ANDROID))).toEqual({
            installed: true,
            inApp: false,
            phone: 'android',
        });
        expect(readPlatform(win(false, GMAIL_IPHONE)).inApp).toBe(true);
    });
});

describe('guideKind', () => {
    const tab = { installed: false, inApp: false };

    it('says nothing once the app is installed, whatever the phone', () => {
        expect(
            guideKind({ installed: true, inApp: false, phone: 'iphone' }),
        ).toBe('hidden');
        expect(
            guideKind({ installed: true, inApp: true, phone: 'android' }),
        ).toBe('hidden');
    });

    it('asks to open it in a real browser before any install step', () => {
        expect(
            guideKind({ installed: false, inApp: true, phone: 'iphone' }),
        ).toBe('in-app');
        expect(
            guideKind({ installed: false, inApp: true, phone: 'android' }),
        ).toBe('in-app');
    });

    it('guides each phone, and nobody else', () => {
        expect(guideKind({ ...tab, phone: 'iphone' })).toBe('iphone');
        expect(guideKind({ ...tab, phone: 'android' })).toBe('android');
        expect(guideKind({ ...tab, phone: 'other' })).toBe('hidden');
    });
});
