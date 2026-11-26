import { DatePicker, InputError, Label } from 'humetria';

export const Seniority = () => (
    <div className="grid gap-2 max-w-xs">
        <Label htmlFor="seniority_on">Fecha de ingreso</Label>
        <DatePicker id="seniority_on" name="seniority_on" defaultValue="2019-03-11" max="2026-11-25" required />
    </div>
);

export const EmptyOptional = () => (
    <div className="grid gap-2 max-w-xs">
        <Label htmlFor="expiry-day">Último día que abre (déjalo vacío para que no venza)</Label>
        <DatePicker id="expiry-day" name="expires_on" min="2026-11-26" />
    </div>
);

export const WithError = () => (
    <div className="grid gap-2 max-w-xs">
        <Label htmlFor="left_on">Fecha de baja</Label>
        <DatePicker
            id="left_on"
            name="left_on"
            defaultValue="2026-10-31"
            invalid
            describedBy="left_on-error"
        />
        <InputError id="left_on-error" message="La baja no puede ser antes de la fecha de ingreso." />
    </div>
);
