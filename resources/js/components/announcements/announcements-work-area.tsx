import AnnouncementForm from '@/components/announcements/announcement-form';
import AnnouncementPreview from '@/components/announcements/announcement-preview';
import AnnouncementsTable from '@/components/announcements/announcements-table';
import MaintenanceCard from '@/components/announcements/maintenance-card';
import type { AnnouncementEditor } from '@/hooks/use-announcement-editor';
import type {
    AnnouncementOption,
    AnnouncementRow,
    MaintenanceState,
    Paginated,
} from '@/types';

/**
 * The announcements and what is done with them (tablero Avisos): the table and the form under it, and beside them how the
 * company will see the banner and the maintenance mode. «Editar» loads a row into the form; saving leaves it blank again.
 */
export default function AnnouncementsWorkArea({
    announcements,
    history,
    catalogs,
    maintenance,
    editor,
}: {
    announcements: Paginated<AnnouncementRow>;
    history: boolean;
    catalogs: { kinds: AnnouncementOption[]; plans: AnnouncementOption[] };
    maintenance: MaintenanceState;
    editor: AnnouncementEditor;
}) {
    const { editing, draft, setDraft, load } = editor;

    return (
        <div className="flex flex-wrap items-start gap-6">
            <div className="flex min-w-0 flex-[999_1_560px] flex-col gap-6">
                <AnnouncementsTable
                    announcements={announcements}
                    history={history}
                    onEdit={load}
                />
                <AnnouncementForm
                    key={editor.key}
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
