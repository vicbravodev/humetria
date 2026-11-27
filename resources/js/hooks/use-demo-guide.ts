import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { xsrfToken } from '@/lib/assistant/xsrf';
import {
    isDemoGuide,
    nextCelebration,
    shownCelebration,
} from '@/lib/demos/guide';
import type { Celebration } from '@/lib/demos/guide';
import {
    guide as guideRoute,
    skip as skipRoute,
} from '@/routes/demo-room/session';
import type { DemoGuide } from '@/types/demo-guide';

const POLL_MS = 3000;

/** The guide an answer of the dock's endpoints carries: anything but a 200 with the guide (no session, a login page) is none. */
async function guideFrom(response: Response): Promise<DemoGuide | null> {
    if (!response.ok || response.redirected) {
        return null;
    }

    const body: unknown = await response.json().catch(() => null);

    return isDemoGuide(body) ? body : null;
}

/** The line that celebrates, held while the guide stays on the step that came after it (the next poll brings none). */
function useCelebration(guide: DemoGuide | null): string | null {
    const [held, setHeld] = useState<Celebration | null>(null);
    const next = nextCelebration(held, guide);

    if (next !== held) {
        setHeld(next);
    }

    return shownCelebration(next, guide);
}

/**
 * The guide of the demo session: the page's (every full visit brings it fresh) and, while the dock is open, its own poll
 * every 3 s with fetch, never a partial reload (one would run the page's controller again, and some write to the audit
 * trail). `skip` moves on from the current step with the guide the server answers.
 */
export function useDemoGuide(polling: boolean): {
    guide: DemoGuide | null;
    celebration: string | null;
    skip: (step: string) => void;
} {
    const shared = usePage().props.demoGuide;
    const [guide, setGuide] = useState(shared);
    const [seen, setSeen] = useState(shared);

    if (shared !== seen) {
        setSeen(shared);
        setGuide(shared);
    }

    const active = guide !== null;

    useEffect(() => {
        if (!active || !polling) {
            return;
        }

        // A new page's guide supersedes a poll still on its way: it would bring the guide back a step.
        const controller = new AbortController();
        const timer = window.setInterval(() => {
            fetch(guideRoute.url(), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then(guideFrom)
                .then(setGuide)
                .catch(() => undefined);
        }, POLL_MS);

        return () => {
            window.clearInterval(timer);
            controller.abort();
        };
    }, [active, polling, shared]);

    const skip = useCallback((step: string) => {
        const xsrf = xsrfToken(document.cookie);

        fetch(skipRoute.url(step), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                ...(xsrf === null ? {} : { 'X-XSRF-TOKEN': xsrf }),
            },
        })
            .then(guideFrom)
            .then(setGuide)
            .catch(() => undefined);
    }, []);

    return { guide, celebration: useCelebration(guide), skip };
}
