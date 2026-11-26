import { useState } from 'react';
import type { AnnouncementRow } from '@/types';

export type AnnouncementDraft = { kind: string; message: string };

const BLANK: AnnouncementDraft = { kind: 'news', message: '' };

/**
 * The announcement the console's form holds (null for a new one), the kind and message its preview follows, and the key that
 * gives a fresh form each time one is loaded: a row to change, or a blank one after a save or «Nuevo aviso».
 */
export function useAnnouncementEditor() {
    const [editing, setEditing] = useState<AnnouncementRow | null>(null);
    const [draft, setDraft] = useState(BLANK);
    const [round, setRound] = useState(0);

    const load = (row: AnnouncementRow | null) => {
        setEditing(row);
        setDraft(
            row === null ? BLANK : { kind: row.kind, message: row.message },
        );
        setRound((value) => value + 1);
        document.getElementById('nuevo-aviso')?.scrollIntoView();
    };

    return {
        editing,
        draft,
        setDraft,
        key: `${editing?.id ?? 'new'}-${round}`,
        load,
    };
}

export type AnnouncementEditor = ReturnType<typeof useAnnouncementEditor>;
