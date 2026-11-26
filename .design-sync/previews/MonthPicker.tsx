import { Label, MonthPicker } from 'humetria';

export const StatementMonth = () => (
    <div className="grid gap-2 max-w-xs">
        <Label htmlFor="statement-month">Mes del estado de cuenta</Label>
        <MonthPicker id="statement-month" name="month" defaultValue="2026-10" max="2026-11" />
    </div>
);

export const Empty = () => (
    <div className="grid gap-2 max-w-xs">
        <Label htmlFor="since-month">Desde qué mes</Label>
        <MonthPicker id="since-month" name="since" />
    </div>
);

export const WithError = () => (
    <div className="grid gap-2 max-w-xs">
        <Label htmlFor="period-month">Mes a cobrar</Label>
        <MonthPicker id="period-month" name="month" defaultValue="2026-12" invalid describedBy="period-month-error" />
        <p id="period-month-error" className="text-sm text-destructive">
            Ese mes todavía no termina.
        </p>
    </div>
);
