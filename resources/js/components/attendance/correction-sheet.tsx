import CorrectionForm from '@/components/attendance/correction-form';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { correctionDay } from '@/lib/attendance/correction';
import type {
    CorrectionAsk,
    CorrectionDraft,
} from '@/lib/attendance/correction';

/**
 * A correction asked as a sheet from the bottom, one mark at a time (board «Asistencia: pedir una corrección»): what is
 * asked and its day at the top, the form under it; the page stays behind. Open while `ask` says what to ask.
 */
export default function CorrectionSheet({
    ask,
    working,
    onSend,
    onClose,
}: {
    ask: CorrectionAsk | null;
    working: boolean;
    onSend: (draft: CorrectionDraft) => void;
    onClose: () => void;
}) {
    return (
        <Sheet
            open={ask !== null}
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
                        {ask?.title}
                    </SheetTitle>
                    <SheetDescription className="text-base">
                        {ask === null ? '' : correctionDay(ask.date)}
                    </SheetDescription>
                </SheetHeader>
                {ask !== null && (
                    <CorrectionForm
                        key={`${ask.date}-${ask.markId ?? 'missing'}`}
                        ask={ask}
                        working={working}
                        onSend={onSend}
                        onCancel={onClose}
                    />
                )}
            </SheetContent>
        </Sheet>
    );
}
