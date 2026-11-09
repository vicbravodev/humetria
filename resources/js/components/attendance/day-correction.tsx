import CorrectionForm from '@/components/attendance/correction-form';
import { Button } from '@/components/ui/button';
import { correctionTarget } from '@/lib/attendance/correction';
import type { CorrectionDraft } from '@/lib/attendance/correction';
import type { WeekMark } from '@/lib/attendance/week';

/** «Me faltó una marca», or the form to ask RH for the correction the person chose on this day. */
export default function DayCorrection({
    date,
    asking,
    working,
    onMissing,
    onClose,
    onCorrect,
}: {
    date: string;
    asking: WeekMark | 'missing' | null;
    working: boolean;
    onMissing: () => void;
    onClose: () => void;
    onCorrect: (draft: CorrectionDraft) => Promise<boolean>;
}) {
    if (asking === null) {
        return (
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="self-start"
                onClick={onMissing}
            >
                Me faltó una marca
            </Button>
        );
    }

    const target = correctionTarget(asking === 'missing' ? null : asking);

    return (
        <CorrectionForm
            key={target.markId ?? 'missing'}
            title={target.title}
            markId={target.markId}
            type={target.type}
            date={date}
            time={target.time}
            working={working}
            onSend={(draft) =>
                void onCorrect(draft).then((sent) => sent && onClose())
            }
            onCancel={onClose}
        />
    );
}
