import { useState } from 'react';
import AnnouncementForm from '@/components/announcements/announcement-form';
import AnnouncementPreview from '@/components/announcements/announcement-preview';
import AnnouncementsTable from '@/components/announcements/announcements-table';
import MaintenanceCard from '@/components/announcements/maintenance-card';
import type {
    AnnouncementOption,
    AnnouncementRow,
    MaintenanceState,
    Paginated,
} from '@/types';

const BLANK = { kind: 'news', message: '' };

/**
 * The announcements and what is done with them (tablero Avisos): the table and the form under it, and beside them how the
 * company will see the banner and the maintenance mode. «Editar» loads a row into the form; saving leaves it blank again.
 */
export default function AnnouncementsWorkArea({
    announcements,
    history,
    catalogs,
    maintenance,
}: {
    announcements: Paginated<AnnouncementRow>;
    history: boolean;
    catalogs: { kinds: AnnouncementOption[]; plans: AnnouncementOption[] };
    maintenance: MaintenanceState;
}) {
    const [editing, setEditing] = useState<AnnouncementRow | null>(null);
    const [draft, setDraft] = useState(BLANK);
    // A new form after each save, so its fields start empty again.
    const [round, setRound] = useState(0);
    const load = (row: AnnouncementRow | null) => {
        setEditing(row);
        setDraft(
            row === null ? BLANK : { kind: row.kind, message: row.message },
        );
        setRound((value) => value + 1);
        document.getElementById('nuevo-aviso')?.scrollIntoView();
    };

    return (
        <div className="flex flex-wrap items-start gap-6">
            <div className="flex min-w-0 flex-[999_1_560px] flex-col gap-6">
                <AnnouncementsTable
                    announcements={announcements}
                    history={history}
                    onEdit={load}
                />
                <AnnouncementForm
                    key={`${editing?.id ?? 'new'}-${round}`}
                    announcement={editing}
                    catalogs={catalogs}
                    draft={{
                        value: draft,
                        onChange: setDraft,
                        onDone: () => load(null),
                    }}
                />
            </div>
            <div className="flex max-w-full min-w-0 flex-[1_1_320px] flex-col gap-6">
                <AnnouncementPreview
                    kind={draft.kind}
                    message={draft.message}
                />
                <MaintenanceCard maintenance={maintenance} />
            </div>
        </div>
    );
}
