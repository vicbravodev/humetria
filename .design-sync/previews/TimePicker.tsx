import { InputError, Label, TimePicker } from 'humetria';

export const ShiftStart = () => (
    <div className="grid gap-2 max-w-xs">
        <Label htmlFor="starts_at">Entrada</Label>
        <TimePicker id="starts_at" name="starts_at" defaultValue="07:30" required />
    </div>
);

export const ShiftRow = () => (
    <div className="flex items-end gap-3">
        <div className="grid gap-2">
            <Label htmlFor="monday-start">Entra</Label>
            <TimePicker id="monday-start" name="days[0][start]" defaultValue="08:00" />
        </div>
        <div className="grid gap-2">
            <Label htmlFor="monday-end">Sale</Label>
            <TimePicker id="monday-end" name="days[0][end]" defaultValue="17:30" />
        </div>
    </div>
);

export const Empty = () => (
    <div className="grid gap-2 max-w-xs">
        <Label htmlFor="mark-time">Hora de la marca</Label>
        <TimePicker id="mark-time" name="time" />
    </div>
);

export const WithError = () => (
    <div className="grid gap-2 max-w-xs">
        <Label htmlFor="ends_at">Salida</Label>
        <TimePicker id="ends_at" name="ends_at" defaultValue="06:00" invalid describedBy="ends_at-error" />
        <InputError id="ends_at-error" message="La salida debe ser después de la entrada (07:30)." />
    </div>
);
