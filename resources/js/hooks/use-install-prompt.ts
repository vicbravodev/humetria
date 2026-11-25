import { useEffect, useState } from 'react';

/** The browser's offer to install the app (Chromium): saved when it fires, launched once. */
type InstallEvent = Event & { prompt: () => Promise<unknown> };

function isInstallEvent(event: Event): event is InstallEvent {
    return 'prompt' in event && typeof event.prompt === 'function';
}

/**
 * Catches `beforeinstallprompt` (and keeps the browser from showing its own bar) and `appinstalled`. `install()` launches the
 * browser's own dialog, once: the event cannot be used twice.
 */
export function useInstallPrompt(): {
    available: boolean;
    installed: boolean;
    install: () => Promise<void>;
} {
    const [offer, setOffer] = useState<InstallEvent | null>(null);
    const [installed, setInstalled] = useState(false);

    useEffect(() => {
        const onOffer = (event: Event) => {
            if (isInstallEvent(event)) {
                event.preventDefault();
                setOffer(event);
            }
        };
        const onInstalled = () => {
            setOffer(null);
            setInstalled(true);
        };

        window.addEventListener('beforeinstallprompt', onOffer);
        window.addEventListener('appinstalled', onInstalled);

        return () => {
            window.removeEventListener('beforeinstallprompt', onOffer);
            window.removeEventListener('appinstalled', onInstalled);
        };
    }, []);

    const install = async () => {
        if (offer === null) {
            return;
        }

        setOffer(null);
        await offer.prompt();
    };

    return { available: offer !== null, installed, install };
}
