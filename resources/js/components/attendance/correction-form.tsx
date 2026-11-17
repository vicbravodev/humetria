import { useState } from 'react';
import CorrectionReasonField from '@/components/attendance/correction-reason-field';
import CorrectionTypeField from '@/components/attendance/correction-type-field';
import TimePicker from '@/components/humetria/time-picker';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type {
    CorrectionAsk,
    CorrectionDraft,
} from '@/lib/attendance/correction';
import type { MarkType } from '@/lib/attendance/next-step';

/**
 * The person asks RH to correct a mark (its type is known) or to add one they forgot (they say which): the right time on
 * their clock, whether it was after midnight, and why. Sending it asks for their fingerprint, face or PIN.
 */
export default function CorrectionForm({
    ask,
    working,
    onSend,
    onCancel,
}: {
    ask: CorrectionAsk;
    working: boolean;
    onSend: (draft: CorrectionDraft) => void;
    onCancel: () => void;
}) {
    const [type, setType] = useState<MarkType>(ask.type ?? 'start');
    const [clock, setClock] = useState(ask.time);
    const [nextDay, setNextDay] = useState(false);
    const [reason, setReason] = useState('');

    return (
        <form
            aria-label={ask.title}
            className="flex flex-col gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                onSend({
                    markId: ask.markId,
                    type,
                    date: ask.date,
                    time: clock,
                    nextDay,
                    reason,
                });
            }}
        >
            {ask.type === null && (
                <CorrectionTypeField value={type} onChange={setType} />
            )}
            <div className="flex flex-col gap-2">
                <Label htmlFor="correction-time" className="text-base">
                    ¿A qué hora fue?
                </Label>
                <TimePicker
                    id="correction-time"
                    name="time"
                    required
                    value={clock}
                    onChange={setClock}
                />
            </div>
            <div className="flex min-h-11 items-center gap-3">
                <Checkbox
                    id="correction-next-day"
                    className="size-[22px]"
                    checked={nextDay}
                    onCheckedChange={(checked) => setNextDay(checked === true)}
                />
                <Label htmlFor="correction-next-day" className="text-base">
                    Fue después de medianoche, al día siguiente
                </Label>
            </div>
            <CorrectionReasonField value={reason} onChange={setReason} />
            <div className="flex flex-col gap-2.5">
                <Button
                    type="submit"
                    className="h-14 w-full text-[17px] font-semibold"
                    disabled={working}
                >
                    Enviar solicitud
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    className="h-14 w-full text-[17px]"
                    disabled={working}
                    onClick={onCancel}
                >
                    Cancelar
                </Button>
            </div>
        </form>
    );
}
