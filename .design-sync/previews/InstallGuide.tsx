import { InstallGuide } from 'humetria';
import type { ReactNode } from 'react';

// InstallGuide reads the phone from `navigator.userAgent` when it mounts and says nothing on a computer: each cell
// sets the phone's user agent while it renders, before the guide reads it.
const IPHONE =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
const ANDROID =
    'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36';
const GMAIL_IOS = `${IPHONE} GSA/340.0`;

function OnPhone({ userAgent, children }: { userAgent: string; children: ReactNode }) {
    Object.defineProperty(window.navigator, 'userAgent', { value: userAgent, configurable: true });

    return <div className="max-w-md">{children}</div>;
}

export const Iphone = () => (
    <OnPhone userAgent={IPHONE}>
        <InstallGuide />
    </OnPhone>
);

export const Android = () => (
    <OnPhone userAgent={ANDROID}>
        <InstallGuide />
    </OnPhone>
);

export const InsideAnotherApp = () => (
    <OnPhone userAgent={GMAIL_IOS}>
        <InstallGuide />
    </OnPhone>
);
