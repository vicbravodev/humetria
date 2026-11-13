import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { deactivationReasons } from '@/lib/employees';

/** The last day of work (never in the future, never before they were hired) and why the relationship ends. */
export default function DepartureFields({
    date,
    onDate,
    reason,
    onReason,
    max,
    min,
}: {
    date: string;
    onDate: (date: string) => void;
    reason: string;
    onReason: (reason: string) => void;
    max: string;
    min: string | null;
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="terminated-on">Último día de trabajo</Label>
                <Input
                    id="terminated-on"
                    type="date"
                    value={date}
                    max={max}
                    {...(min === null ? {} : { min })}
                    onChange={(event) => onDate(event.target.value)}
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="deactivation-reason">Motivo</Label>
                <Select value={reason} onValueChange={onReason}>
                    <SelectTrigger id="deactivation-reason">
                        <SelectValue placeholder="Elige un motivo" />
                    </SelectTrigger>
                    <SelectContent>
                        {deactivationReasons.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <p className="text-sm text-muted-foreground sm:col-span-2">
                Los días de vacaciones pendientes se calculan a esta fecha.
            </p>
        </div>
    );
}
