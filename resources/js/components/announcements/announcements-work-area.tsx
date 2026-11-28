import AnnouncementSheet from '@/components/announcements/announcement-sheet';
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
 * The announcements and what is done with them (tablero Avisos): the table, the maintenance mode beside it, and the side
 * panel where one is written or changed with its preview. «Editar» opens a row in it; saving closes it.
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
    return (
        <div className="flex flex-wrap items-start gap-6">
            <div className="flex min-w-0 flex-[999_1_560px] flex-col gap-6">
                <AnnouncementsTable
                    announcements={announcements}
                    history={history}
                    onEdit={editor.load}
                />
            </div>
            <div className="flex max-w-full min-w-0 flex-[1_1_320px] flex-col gap-6">
                <MaintenanceCard maintenance={maintenance} />
            </div>
            <AnnouncementSheet editor={editor} catalogs={catalogs} />
        </div>
    );
}
