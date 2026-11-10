import { useState } from 'react';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { LeaveWorkWeek } from '@/types';

/** The days that count as working days when the policy's people ask for vacations. */
export default function PolicyWorkWeekField({
    defaultValue,
    error,
}: {
    defaultValue: LeaveWorkWeek;
    error: string | undefined;
}) {
    const [week, setWeek] = useState<LeaveWorkWeek>(defaultValue);

    return (
        <div className="grid gap-2">
            <Label htmlFor="policy-work-week">Semana laboral</Label>
            <input type="hidden" name="work_week" value={week} />
            <Select
                value={week}
                onValueChange={(next) =>
                    setWeek(next === 'mon_sat' ? 'mon_sat' : 'mon_fri')
                }
            >
                <SelectTrigger id="policy-work-week" className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="mon_fri">De lunes a viernes</SelectItem>
                    <SelectItem value="mon_sat">De lunes a sábado</SelectItem>
                </SelectContent>
            </Select>
            <p className="text-caption text-muted-foreground">
                Los días que cuentan como laborables al pedir vacaciones.
            </p>
            <InputError message={error} />
        </div>
    );
}
