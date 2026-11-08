import { useState } from 'react';
import ScheduleDaysField from '@/components/attendance/schedule-days-field';
import ChoiceField from '@/components/humetria/choice-field';
import FormDialog from '@/components/humetria/form-dialog';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { update } from '@/routes/tenant/attendance/schedules';
import type { SchedulePerson, WeeklyMaximum, WorkSchedule } from '@/types';

const SHIFTS: { value: WorkSchedule['shiftType']; label: string }[] = [
    { value: 'day', label: 'Diurna' },
    { value: 'night', label: 'Nocturna' },
    { value: 'mixed', label: 'Mixta' },
];

const MODES: { value: WorkSchedule['workMode']; label: string }[] = [
    { value: 'onsite', label: 'Presencial' },
    { value: 'remote', label: 'Remoto' },
];

/** RH fixes a person's agreed schedule: their shift, where they work and the hours of each day, with the week's total. */
export default function ScheduleDialog({
    open,
    onOpenChange,
    person,
    weeklyMaximum,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    person: SchedulePerson;
    weeklyMaximum: WeeklyMaximum;
}) {
    const tenantSlug = useTenantSlug();
    const [shift, setShift] = useState(person.schedule?.shiftType ?? 'day');
    const [mode, setMode] = useState(person.schedule?.workMode ?? 'onsite');

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={`Horario de ${person.name}`}
            description="El horario que acordó con la empresa: con él se cuentan sus horas extra y sus días de descanso."
            form={update.form({ tenant: tenantSlug, employee: person.id })}
            submit="Guardar horario"
        >
            {(errors) => (
                <div className="grid gap-5">
                    <ChoiceField
                        name="shift_type"
                        legend="Jornada"
                        options={SHIFTS}
                        value={shift}
                        onChange={setShift}
                        columns={3}
                        error={errors['shift_type']}
                    />
                    <ChoiceField
                        name="work_mode"
                        legend="Modalidad"
                        options={MODES}
                        value={mode}
                        onChange={setMode}
                        columns={2}
                        error={errors['work_mode']}
                    />
                    <ScheduleDaysField
                        initial={person.schedule?.minutes ?? null}
                        weeklyMaximum={weeklyMaximum}
                        errors={errors}
                    />
                </div>
            )}
        </FormDialog>
    );
}
