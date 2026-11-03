import { useEffect, useState } from 'react';
import type { HumiExpression } from '@/lib/assistant/humi-expressions';
import { moodOf } from '@/lib/assistant/humi-mood';

const FINISHED_MS = 2500;

/** Humi's face for the panel: it goes back to resting a moment after it finishes an answer. */
export function useHumiMood(
    busy: boolean,
    failed: boolean,
    writing: boolean,
): HumiExpression {
    const [wasBusy, setWasBusy] = useState(busy);
    const [finished, setFinished] = useState(false);

    // Seeing the answer end is derived while rendering, so the face never flashes the old one for a frame.
    if (busy !== wasBusy) {
        setWasBusy(busy);
        setFinished(wasBusy && !busy && !failed);
    }

    useEffect(() => {
        if (!finished) {
            return;
        }

        const timer = window.setTimeout(() => setFinished(false), FINISHED_MS);

        return () => window.clearTimeout(timer);
    }, [finished]);

    return moodOf({ busy, failed, writing, finished });
}
