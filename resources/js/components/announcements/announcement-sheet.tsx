import AnnouncementForm from '@/components/announcements/announcement-form';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { AnnouncementEditor } from '@/hooks/use-announcement-editor';
import type { AnnouncementOption } from '@/types';

/**
 * A new announcement, or the one being changed, in the right side panel with the list behind it: the shape of `FormSheet`,
 * with two ways to save (on its date or as a draft) instead of one.
 */
export default function AnnouncementSheet({
    editor,
    catalogs,
}: {
    editor: AnnouncementEditor;
    catalogs: { kinds: AnnouncementOption[]; plans: AnnouncementOption[] };
}) {
    const { editing, draft, setDraft, open, setOpen } = editor;

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetContent className="w-full gap-0 bg-popover sm:inset-y-2 sm:right-2 sm:h-auto sm:max-w-[520px] sm:rounded-xl sm:border-0 sm:shadow-float">
                <SheetHeader className="gap-0.5 border-b px-6 pt-5 pb-4">
                    <SheetTitle className="text-lg leading-[26px]">
                        {editing === null ? 'Nuevo aviso' : 'Editar aviso'}
                    </SheetTitle>
                    <SheetDescription>
                        Las horas son de la Ciudad de México. Un borrador nunca
                        llega a los paneles.
                    </SheetDescription>
                </SheetHeader>
                <AnnouncementForm
                    key={editor.key}
                    announcement={editing}
                    catalogs={catalogs}
                    draft={{
                        value: draft,
                        onChange: setDraft,
                        onDone: () => setOpen(false),
                    }}
                />
            </SheetContent>
        </Sheet>
    );
}
