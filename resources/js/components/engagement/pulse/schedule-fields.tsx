import { useState } from 'react';
import ChoiceField from '@/components/humetria/choice-field';
import InputError from '@/components/humetria/input-error';
import PillChoice from '@/components/humetria/pill-choice';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PulseModeOption, PulseSchedule } from '@/types';

type Errors = Partial<Record<string, string>>;

/** The fields of the schedule: how often, which day, how many days open, and what the next pulse looks at. */
export default function ScheduleFields({
    schedule,
    frequencies,
    modes,
    errors,
}: {
    schedule: PulseSchedule | null;
    frequencies: PulseModeOption[];
    modes: PulseModeOption[];
    errors: Errors;
}) {
    const [frequency, setFrequency] = useState(
        schedule?.frequency ?? 'monthly',
    );
    const [next, setNext] = useState(schedule?.nextMode ?? '');

    return (
        <>
            <ChoiceField
                name="frequency"
                legend="¿Cada cuándo sale?"
                options={frequencies}
                value={frequency}
                onChange={setFrequency}
                columns={2}
                error={errors['frequency']}
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="pulse-day">Día del mes (1 a 28)</Label>
                    <Input
                        id="pulse-day"
                        name="day_of_month"
                        type="number"
                        inputMode="numeric"
                        min={1}
                        max={28}
                        defaultValue={schedule?.dayOfMonth ?? 15}
                        aria-invalid={errors['day_of_month'] !== undefined}
                    />
                    <InputError message={errors['day_of_month']} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="pulse-open">Días abierto (3 a 14)</Label>
                    <Input
                        id="pulse-open"
                        name="open_days"
                        type="number"
                        inputMode="numeric"
                        min={3}
                        max={14}
                        defaultValue={schedule?.openDays ?? 7}
                        aria-invalid={errors['open_days'] !== undefined}
                    />
                    <InputError message={errors['open_days']} />
                </div>
            </div>
            <PillChoice
                name="next_mode"
                legend="El siguiente pulso mira"
                options={[{ value: '', label: 'Lo que toque' }, ...modes]}
                value={next}
                onChange={setNext}
            />
            <InputError message={errors['next_mode']} />
        </>
    );
}
