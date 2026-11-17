import { useState } from 'react';
import { useNow } from '@/hooks/use-now';
import { clockOffset } from '@/lib/attendance/punch-view';

/**
 * The server's time, renewed every `everyMs`: the phone's clock plus how far it was from the server's when the page came
 * (`server`, in milliseconds). A phone with the wrong time or date still shows the server's.
 */
export function useServerNow(server: number, everyMs: number): number {
    const [offset] = useState(() => clockOffset(server, Date.now()));

    return useNow(everyMs) + offset;
}
