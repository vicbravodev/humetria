import { useState } from 'react';
import CorrectionReasonField from '@/components/attendance/correction-reason-field';
import CorrectionTypeField from '@/components/attendance/correction-type-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { correctionDay } from '@/lib/attendance/correction';
import type { CorrectionDraft } from '@/lib/attendance/correction';
import type { MarkType } from '@/lib/attendance/next-step';

/**
 * The person asks RH to correct a mark (its type is known) or to add one they forgot (they say which): the right time on
 * their clock, whether it was after midnight, and why. Sending it asks for their fingerprint, face or PIN.
 */
export default function CorrectionForm({
    title,
    markId,
    type: fixedType,
    date,
    time,
    working,
    onSend,
    onCancel,
}: {
    title: string;
    markId: string | null;
    type: MarkType | null;
    date: string;
    time: string;
    working: boolean;
    onSend: (draft: CorrectionDraft) => void;
    onCancel: () => void;
}) {
    const [type, setType] = useState<MarkType>(fixedType ?? 'start');
    const [clock, setClock] = useState(time);
    const [nextDay, setNextDay] = useState(false);
    const [reason, setReason] = useState('');

    return (
        <form
            aria-label={title}
            className="flex flex-col gap-4 rounded-lg border bg-muted/40 p-4"
            onSubmit={(event) => {
                event.preventDefault();
                onSend({ markId, type, date, time: clock, nextDay, reason });
            }}
        >
            <p className="text-base font-semibold">{title}</p>
            <p className="text-base">{correctionDay(date)}</p>
            {fixedType === null && (
                <CorrectionTypeField value={type} onChange={setType} />
            )}
            <div className="flex flex-col gap-2">
                <Label htmlFor="correction-time" className="text-base">
                    ¿A qué hora fue?
                </Label>
                <Input
                    id="correction-time"
                    name="time"
                    type="time"
                    required
                    value={clock}
                    onChange={(event) => setClock(event.target.value)}
                    className="text-base"
                />
            </div>
            <div className="flex items-start gap-3">
                <Checkbox
                    id="correction-next-day"
                    className="mt-1"
                    checked={nextDay}
                    onCheckedChange={(checked) => setNextDay(checked === true)}
                />
                <Label htmlFor="correction-next-day" className="text-base">
                    Fue después de medianoche, al día siguiente.
                </Label>
            </div>
            <CorrectionReasonField value={reason} onChange={setReason} />
            <div className="flex flex-col gap-2">
                <Button
                    type="submit"
                    size="lg"
                    className="w-full text-base"
                    disabled={working}
                >
                    Enviar solicitud
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="lg"
                    className="w-full text-base"
                    disabled={working}
                    onClick={onCancel}
                >
                    Cancelar
                </Button>
            </div>
        </form>
    );
}
