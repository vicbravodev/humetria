import { useEffect } from 'react';
import { OPEN_HUMI } from '@/lib/assistant/open-humi';

/** Opens Humi when a page asks for it (`openHumi()`). */
export function useHumiOpenRequest(open: () => void): void {
    useEffect(() => {
        window.addEventListener(OPEN_HUMI, open);

        return () => window.removeEventListener(OPEN_HUMI, open);
    }, [open]);
}
