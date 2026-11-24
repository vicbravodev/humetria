import { useState } from 'react';
import { useLocationPermission } from '@/hooks/use-location-permission';
import { useOfflineMark } from '@/hooks/use-offline-mark';
import { useOfflineQueue } from '@/hooks/use-offline-queue';
import { useTickets } from '@/hooks/use-tickets';
import { currentPosition } from '@/lib/attendance/geolocation';
import type { MarkType } from '@/lib/attendance/next-step';
import { offlineReadiness } from '@/lib/attendance/offline-readiness';
import {
    clockOf,
    declaredFromTime,
    secondsOf,
} from '@/lib/attendance/offline-time';
import { positionOf } from '@/lib/pwa/challenge';
import type { PreparedMark } from '@/lib/pwa/offline-mark';

/** Which screen replaces Marcar while a mark made without a connection is dealt with. */
export type OfflineView =
    | 'saved'
    | 'plan_b'
    | 'plan_b_unavailable'
    | 'not_ready';

/** The mark under way: what is signed again when the person retries or marks with the phone. */
type Attempt = {
    type: MarkType;
    qr: string | null;
    declaredAt: number;
    justification: string | null;
};

/** The phone's position as the integers that are signed, or none: it never blocks a mark made without a connection. */
async function whereAmI() {
    const located = await currentPosition(
        'geolocation' in navigator ? navigator.geolocation : undefined,
        10_000,
    );

    return located.ok
        ? positionOf({
              latitude: located.position.lat,
              longitude: located.position.lng,
              accuracy: located.position.accuracy,
          })
        : null;
}

/** The mark of a tap with a code read: the hour is the phone's at the tap, and the code only sets a «not before». */
function tapAttempt(type: MarkType, code: string): Attempt {
    return {
        type,
        qr: code,
        declaredAt: secondsOf(new Date()),
        justification: null,
    };
}

/** The mark of the sheet: the hour the person wrote (the phone's now if it cannot be read) and their note, if any. */
function sheetAttempt(
    type: MarkType,
    draft: { time: string; note: string },
): Attempt {
    const note = draft.note.trim();

    return {
        type,
        qr: null,
        declaredAt:
            declaredFromTime(new Date(), draft.time) ?? secondsOf(new Date()),
        justification: note === '' ? null : note,
    };
}

/** The screen a result asks for: saved; plan B when the passkey did not sign (with the phone's key only if it has one). */
function viewOf(prepared: PreparedMark): OfflineView {
    switch (prepared.kind) {
        case 'saved':
            return 'saved';
        case 'cancelled':
            return prepared.deviceKey ? 'plan_b' : 'plan_b_unavailable';
        case 'not-ready':
            return 'not_ready';
        default:
            return 'plan_b_unavailable';
    }
}

/**
 * Marking without a connection, from the tap to the queue: whether the phone is ready, the sheet that asks the hour and the
 * note (for a mark without a QR), the position (never blocks), the signature and what the person sees after: «Guardada sin
 * conexión», plan B when their key does not answer, or what is missing. The queue and the tickets are here too, filtered to the
 * person on the screen. It needs no page of the server: the shell of the installed app uses it as the page of Marcar does.
 */
export function useOfflineFlow({
    slug,
    deliverUrl,
    ticketsUrl,
}: {
    slug: string;
    deliverUrl: string;
    ticketsUrl: string;
}) {
    const queue = useOfflineQueue({ slug, deliverUrl });
    const tickets = useTickets({ slug, ticketsUrl });
    const marking = useOfflineMark(slug);
    const location = useLocationPermission();
    const [view, setView] = useState<OfflineView | null>(null);
    const [asking, setAsking] = useState<MarkType | null>(null);
    const [last, setLast] = useState<Attempt | null>(null);
    const [saved, setSaved] = useState<string | null>(null);

    const readiness = offlineReadiness({
        context: tickets.context,
        tickets: tickets.count,
        camera: 'unknown',
        location,
        practice: tickets.practice?.result === 'failed' ? 'failed' : 'passkey',
        passValid: tickets.passValid,
        remote: false,
    });

    const run = async (attempt: Attempt, useDeviceKey: boolean) => {
        setLast(attempt);
        const prepared = await marking.mark({
            ...attempt,
            position: await whereAmI(),
            useDeviceKey,
        });

        setAsking(null);
        setView(viewOf(prepared));
        setSaved(
            prepared.kind === 'saved' ? clockOf(attempt.declaredAt) : null,
        );
    };

    return {
        view,
        asking,
        saved,
        busy: marking.busy,
        readiness,
        queue,
        tickets,
        /** A tap that could not reach the server: with a QR it is signed at once, without one the sheet asks the hour. */
        start: (type: MarkType, code: string | null) => {
            if (!readiness.ready) {
                setView('not_ready');
            } else if (code === null) {
                setAsking(type);
            } else {
                void run(tapAttempt(type, code), false);
            }
        },
        /** The sheet: the hour the person says (HH:mm) and their note. */
        submit: (draft: { time: string; note: string }) => {
            if (asking !== null) {
                void run(sheetAttempt(asking, draft), false);
            }
        },
        closeSheet: () => setAsking(null),
        markWithPhone: () => last !== null && void run(last, true),
        retryPasskey: () => last !== null && void run(last, false),
        openReadiness: () => setView('not_ready'),
        dismiss: () => {
            setView(null);
            setSaved(null);
            setAsking(null);
        },
    };
}

export type OfflineFlow = ReturnType<typeof useOfflineFlow>;
