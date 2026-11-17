import { useState } from 'react';
import ChoiceField from '@/components/humetria/choice-field';
import { Button } from '@/components/ui/button';
import { shiftLabel } from '@/lib/attendance/schedule';
import type { WorkSchedule } from '@/types';

type Shift = WorkSchedule['shiftType'];

const SHIFTS: { value: Shift; label: string }[] = [
    { value: 'day', label: 'Diurna' },
    { value: 'night', label: 'Nocturna' },
    { value: 'mixed', label: 'Mixta' },
];

/**
 * The shift, not a question: the one the week's hours say (art. 60), as a tag with «cambiar» for when RH knows better.
 * It names the shift and its daily limit; no count changes with it (criterio 2.9).
 */
export default function ScheduleShiftTag({
    shift,
    onChange,
    error,
}: {
    shift: Shift;
    onChange: (shift: Shift) => void;
    error: string | undefined;
}) {
    const [changing, setChanging] = useState(error !== undefined);

    if (changing) {
        return (
            <ChoiceField
                name="shift_type"
                legend="Jornada"
                options={SHIFTS}
                value={shift}
                onChange={onChange}
                columns={3}
                error={error}
            />
        );
    }

    return (
        <div className="flex flex-wrap items-center gap-2 text-sm">
            <input type="hidden" name="shift_type" value={shift} />
            <span>
                <span className="text-muted-foreground">Jornada </span>
                <span className="font-medium">
                    {shiftLabel(shift).toLowerCase()}
                </span>
            </span>
            <Button
                type="button"
                variant="link"
                size="sm"
                className="h-auto px-0"
                aria-label="Cambiar la jornada"
                onClick={() => setChanging(true)}
            >
                cambiar
            </Button>
        </div>
    );
}
