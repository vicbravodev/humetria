import PunchOfflineForm from '@/components/attendance/punch-offline-form';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { markName } from '@/lib/attendance/next-step';
import type { MarkType } from '@/lib/attendance/next-step';

/**
 * A mark without a connection and without a QR, as a sheet from the bottom: the hour and the note, then the person's
 * fingerprint, face or PIN. With a QR read there is no sheet: the hour is the phone's at the tap and the code only sets a
 * «not before». Open while `type` says which mark.
 */
export default function PunchOfflineSheet({
    type,
    busy,
    onSave,
    onClose,
}: {
    type: MarkType | null;
    busy: boolean;
    onSave: (draft: { time: string; note: string }) => void;
    onClose: () => void;
}) {
    return (
        <Sheet
            open={type !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <SheetContent
                side="bottom"
                className="max-h-[92dvh] gap-4 overflow-y-auto rounded-t-2xl bg-popover px-4 pt-5 pb-7"
            >
                <SheetHeader className="gap-1 p-0 pr-12">
                    <SheetTitle className="text-[22px] leading-[30px]">
                        {type === null
                            ? 'Marcar sin internet'
                            : `${markName(type)} sin internet`}
                    </SheetTitle>
                    <SheetDescription className="text-base">
                        No hay internet: su marca se guarda en este teléfono y
                        se envía sola.
                    </SheetDescription>
                </SheetHeader>
                {type !== null && (
                    <PunchOfflineForm busy={busy} onSave={onSave} />
                )}
            </SheetContent>
        </Sheet>
    );
}
