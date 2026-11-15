import { useEffect } from 'react';
import { OPEN_HUMI } from '@/lib/assistant/open-humi';

/** Opens Humi when a page asks for it (`openHumi()`), with the draft it brings, if any. */
export function useHumiOpenRequest(open: (draft: string | null) => void): void {
    useEffect(() => {
        const listen = (event: Event) => {
            const draft =
                event instanceof CustomEvent &&
                typeof event.detail?.draft === 'string'
                    ? event.detail.draft
                    : null;

            open(draft);
        };

        window.addEventListener(OPEN_HUMI, listen);

        return () => window.removeEventListener(OPEN_HUMI, listen);
    }, [open]);
}
