import { useState } from 'react';
import type { AnnouncementRow } from '@/types';

export type AnnouncementDraft = { kind: string; message: string };

const BLANK: AnnouncementDraft = { kind: 'news', message: '' };

/**
 * The announcement the console's side panel holds (null for a new one), whether the panel is open, the kind and message its
 * preview follows, and the key that gives a fresh form each time one is loaded: a row to change, or a blank one.
 */
export function useAnnouncementEditor() {
    const [editing, setEditing] = useState<AnnouncementRow | null>(null);
    const [draft, setDraft] = useState(BLANK);
    const [round, setRound] = useState(0);
    const [open, setOpen] = useState(false);

    /** Opens the side panel with a row to change, or blank for a new one. */
    const load = (row: AnnouncementRow | null) => {
        setEditing(row);
        setDraft(
            row === null ? BLANK : { kind: row.kind, message: row.message },
        );
        setRound((value) => value + 1);
        setOpen(true);
    };

    return {
        editing,
        draft,
        setDraft,
        key: `${editing?.id ?? 'new'}-${round}`,
        load,
        open,
        setOpen,
    };
}

export type AnnouncementEditor = ReturnType<typeof useAnnouncementEditor>;
