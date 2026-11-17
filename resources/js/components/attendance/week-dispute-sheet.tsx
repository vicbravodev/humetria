import DisputeForm from '@/components/attendance/dispute-form';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

/** «No estoy de acuerdo» in the same sheet from the bottom as a correction (hallazgo 22): the reason, then send or cancel. */
export default function WeekDisputeSheet({
    open,
    working,
    onSend,
    onClose,
}: {
    open: boolean;
    working: boolean;
    onSend: (reason: string) => void;
    onClose: () => void;
}) {
    return (
        <Sheet
            open={open}
            onOpenChange={(next) => {
                if (!next) {
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
                        No estoy de acuerdo
                    </SheetTitle>
                    <SheetDescription className="text-base">
                        Diga qué no está bien; Recursos Humanos lo revisará.
                    </SheetDescription>
                </SheetHeader>
                <DisputeForm
                    working={working}
                    onSend={onSend}
                    onCancel={onClose}
                />
            </SheetContent>
        </Sheet>
    );
}
