import { useCallback, useEffect, useRef, useState } from 'react';
import { useEscape } from '@/hooks/use-escape';
import { useHumiLiveFacts } from '@/hooks/use-humi-live-facts';
import {
    mayInvite,
    whenAccepted,
    whenNeverAgain,
    whenNotNow,
    whenOpened,
    whenShown,
} from '@/lib/assistant/invitation-rules';
import {
    loadRemembered,
    loadSession,
    saveRemembered,
    saveSession,
} from '@/lib/assistant/invitation-store';
import { invitationPageOf } from '@/lib/assistant/page-context';

/** How often the bubble asks itself whether it may invite: a read of the browser's storage, cheap enough each second. */
const CHECK_EVERY = 1_000;

export type Invitation = { page: string; message: string };

/** A dialog, a sheet or the first steps tour is open: the bubble does not interrupt it. */
function somethingOpen(): boolean {
    return (
        document.body.classList.contains('driver-active') ||
        document.querySelector('[role="dialog"], [role="alertdialog"]') !== null
    );
}

/**
 * Whether the closed bubble invites now on the page open, and what the person answers. The message is the fact
 * `invite.{page}` of the server (none, no invitation); what they declined is kept in the browser under `storageKey`.
 * Opening Humi by any way ends the invitations of the session.
 */
export function useHumiInvitation({
    slug,
    storageKey,
    path,
    humiOpen,
}: {
    slug: string | null;
    storageKey: string;
    path: string;
    humiOpen: boolean;
}) {
    const page = invitationPageOf(path);
    const facts = useHumiLiveFacts(page === null ? null : slug);
    const message = page === null ? undefined : facts[`invite.${page}`];
    const arrivedAt = useRef(0);
    const [shown, setShown] = useState<Invitation | null>(null);
    const [shownPath, setShownPath] = useState(path);

    // It belongs to the page where it showed: leaving the page drops it.
    if (shownPath !== path) {
        setShownPath(path);
        setShown(null);
    }

    // And it goes for good the moment Humi opens.
    if (humiOpen && shown !== null) {
        setShown(null);
    }

    const invitation = humiOpen ? null : shown;
    const dismiss = useCallback(() => setShown(null), []);

    useEffect(() => {
        arrivedAt.current = Date.now();
    }, [path]);

    useEffect(() => {
        if (humiOpen) {
            saveSession(storageKey, whenOpened(loadSession(storageKey)));
        }
    }, [humiOpen, storageKey]);

    useEffect(() => {
        if (page === null || message === undefined || invitation !== null) {
            return;
        }

        const check = () => {
            const now = Date.now();
            const remembered = loadRemembered(storageKey);
            const session = loadSession(storageKey);
            const moment = {
                now,
                arrivedAt: arrivedAt.current,
                page,
                hasMessage: true,
                busy: humiOpen || somethingOpen(),
            };

            if (mayInvite(remembered, session, moment)) {
                const [kept, tab] = whenShown(remembered, session, {
                    page,
                    now,
                });
                saveRemembered(storageKey, kept);
                saveSession(storageKey, tab);
                setShown({ page, message });
            }
        };
        const timer = window.setInterval(check, CHECK_EVERY);

        return () => window.clearInterval(timer);
    }, [page, message, invitation, humiOpen, storageKey]);

    useEscape(invitation !== null, dismiss);

    return {
        invitation,
        dismiss,
        notNow: () => {
            saveRemembered(
                storageKey,
                whenNotNow(loadRemembered(storageKey), Date.now()),
            );
            dismiss();
        },
        neverAgain: () => {
            saveRemembered(
                storageKey,
                whenNeverAgain(loadRemembered(storageKey)),
            );
        },
        accept: () => {
            const [kept, tab] = whenAccepted(
                loadRemembered(storageKey),
                loadSession(storageKey),
            );
            saveRemembered(storageKey, kept);
            saveSession(storageKey, tab);
            dismiss();
        },
    };
}
