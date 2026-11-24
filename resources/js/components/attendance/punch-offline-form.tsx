import { useState } from 'react';
import TimePicker from '@/components/humetria/time-picker';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { clockOf, secondsOf } from '@/lib/attendance/offline-time';

const NOTE_MAX = 500;

/**
 * The hour the person says (the phone's now to start with) and, if they want, what happened: the note is only for RH, who read
 * it with the mark. The hour is bounded by the server with what it can prove, and RH verifies it.
 */
export default function PunchOfflineForm({
    busy,
    onSave,
}: {
    busy: boolean;
    onSave: (draft: { time: string; note: string }) => void;
}) {
    const [time, setTime] = useState(() => clockOf(secondsOf(new Date())));
    const [note, setNote] = useState('');

    return (
        <form
            aria-label="Marcar sin internet"
            className="flex flex-col gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                onSave({ time, note });
            }}
        >
            <div className="flex flex-col gap-2">
                <Label htmlFor="offline-time" className="text-base">
                    Hora en que marca
                </Label>
                <TimePicker
                    id="offline-time"
                    name="time"
                    required
                    value={time}
                    onChange={setTime}
                />
                <p className="text-meta text-muted-foreground">
                    El servidor la acota con lo que sí puede comprobar y
                    Recursos Humanos la verifica.
                </p>
            </div>
            <div className="flex flex-col gap-2">
                <Label htmlFor="offline-note" className="text-base">
                    ¿Qué pasó? (opcional)
                </Label>
                <Textarea
                    id="offline-note"
                    name="note"
                    rows={3}
                    maxLength={NOTE_MAX}
                    className="text-base"
                    value={note}
                    onChange={(event) => setNote(event.target.value)}
                />
                <p className="text-meta text-muted-foreground tabular-nums">
                    {note.length} de {NOTE_MAX}
                </p>
            </div>
            <Button
                type="submit"
                className="h-14 w-full text-[17px] font-semibold"
                disabled={busy || time === ''}
            >
                Guardar con su huella
            </Button>
        </form>
    );
}
