import { deviceLabel } from '@/lib/webauthn';

export type Phone = 'iphone' | 'android' | 'other';

export type Platform = {
    installed: boolean;
    inApp: boolean;
    phone: Phone;
};

export type GuideKind = 'hidden' | 'in-app' | Exclude<Phone, 'other'>;

/** Whether the app runs installed (its own window) and not in a browser tab: `display-mode`, or iOS's `navigator.standalone`. */
export function isInstalled({
    standaloneMedia,
    navigator,
}: {
    standaloneMedia: boolean;
    navigator: object;
}): boolean {
    return (
        standaloneMedia ||
        ('standalone' in navigator && navigator.standalone === true)
    );
}

// The browsers inside Gmail, Outlook, Facebook, Instagram, Line, WeChat and any Android WebView. A short list on purpose: one
// that is not listed takes the normal way.
const IN_APP = /FBAN|FBAV|Instagram|Line\/|GSA\/|Outlook|MicroMessenger|; wv\)/;

/** Whether the page was opened inside another app's browser, which can neither install it nor keep its data. */
export function inAppBrowser(userAgent: string): boolean {
    return IN_APP.test(userAgent);
}

/** The phone that opens the page; an iPad, also the one that says it is a Mac but has a touch screen, installs like an iPhone. */
export function phoneOf(userAgent: string, maxTouchPoints: number): Phone {
    const device = deviceLabel(userAgent);

    if (device === 'iPhone' || device === 'iPad') {
        return 'iphone';
    }

    if (device === 'Android') {
        return 'android';
    }

    return device === 'Mac' && maxTouchPoints > 1 ? 'iphone' : 'other';
}

/** Ask the browser to keep the app's data only once it is installed and has not been granted that yet. */
export function shouldAskPersistence(
    installed: boolean,
    persisted: boolean,
): boolean {
    return installed && !persisted;
}

/** What the page knows about where it runs; `win` is the window, so a test can hand in any phone. */
export function readPlatform(win: {
    matchMedia: (query: string) => { matches: boolean };
    navigator: { userAgent: string; maxTouchPoints: number };
}): Platform {
    const { navigator } = win;

    return {
        installed: isInstalled({
            standaloneMedia: win.matchMedia('(display-mode: standalone)')
                .matches,
            navigator,
        }),
        inApp: inAppBrowser(navigator.userAgent),
        phone: phoneOf(navigator.userAgent, navigator.maxTouchPoints),
    };
}

/** Which guide the person needs: none when installed (or not on a phone), «Ábralo en Safari o Chrome» before any step. */
export function guideKind({ installed, inApp, phone }: Platform): GuideKind {
    if (installed) {
        return 'hidden';
    }

    if (inApp) {
        return 'in-app';
    }

    return phone === 'other' ? 'hidden' : phone;
}
