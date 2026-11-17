import { useState } from 'react';
import type { PageContext } from '@/lib/assistant/chat-request';

/**
 * The page Humi is looking at: the one open, until the person removes it. Removing it holds for that page only: another
 * page open comes along again.
 */
export function useHumiSeenPage(page: PageContext): {
    seen: PageContext;
    drop: () => void;
} {
    const [dropped, setDropped] = useState<PageContext>(null);
    const seen = page !== null && dropped?.id === page.id ? null : page;

    return { seen, drop: () => setDropped(seen) };
}
