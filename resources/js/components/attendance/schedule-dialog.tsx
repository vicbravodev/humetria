import ScheduleDaysField from '@/components/attendance/schedule-days-field';
import SchedulePreview from '@/components/attendance/schedule-preview';
import SchedulePeopleInputs from '@/components/attendance/schedule-people-inputs';
import ScheduleStartsField from '@/components/attendance/schedule-starts-field';
import ScheduleTemplates from '@/components/attendance/schedule-templates';
import ChoiceField from '@/components/humetria/choice-field';
import InputError from '@/components/humetria/input-error';
import FormDialog from '@/components/humetria/form-dialog';
import { useScheduleDraft } from '@/hooks/use-schedule-draft';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import {
    peopleNames,
    replacingText,
    scheduleTemplates,
} from '@/lib/attendance/schedule';
import { bulk, update } from '@/routes/tenant/attendance/schedules';
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

/**
 * RH fixes the agreed schedule of one person, or the same one for everyone chosen: a template to start from, their shift,
 * where they work and the hours of each day, drawn as the week with its total. For several, it says who loses the schedule
 * they had.
 */
export default function ScheduleDialog({
    open,
    onOpenChange,
    people,
    weeklyMaximum,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    people: SchedulePerson[];
    weeklyMaximum: WeeklyMaximum;
}) {
    const tenantSlug = useTenantSlug();
    const [only] = people;
    const draft = useScheduleDraft(
        people.length === 1 ? (only?.schedule ?? null) : null,
    );
    const replacing = replacingText(people);
    const names = peopleNames(people.map((person) => person.name));

    return (
        <FormDialog
            wide
            open={open}
            onOpenChange={onOpenChange}
            title={
                people.length === 1
                    ? `Horario de ${names}`
                    : `Horario para ${people.length} personas`
            }
            description={
                people.length === 1
                    ? 'El horario que acordó con la empresa: con él se cuentan sus horas extra y sus días de descanso.'
                    : `${names}. El mismo horario para todas: con él se cuentan sus horas extra y sus días de descanso. ${replacing ?? ''}`
            }
            form={
                only !== undefined && people.length === 1
                    ? update.form({ tenant: tenantSlug, employee: only.id })
                    : bulk.form(tenantSlug)
            }
            submit={
                people.length === 1
                    ? 'Guardar horario'
                    : `Guardar horario en ${people.length} personas`
            }
        >
            {(errors) => (
                <div className="grid gap-5">
                    <SchedulePeopleInputs people={people} />
                    <ScheduleTemplates
                        templates={scheduleTemplates(weeklyMaximum)}
                        onPick={draft.apply}
                    />
                    <ChoiceField
                        name="shift_type"
                        legend="Jornada"
                        options={SHIFTS}
                        value={draft.shift}
                        onChange={draft.setShift}
                        columns={3}
                        error={errors['shift_type']}
                    />
                    <ChoiceField
                        name="work_mode"
                        legend="Modalidad"
                        options={MODES}
                        value={draft.mode}
                        onChange={draft.setMode}
                        columns={2}
                        error={errors['work_mode']}
                    />
                    <ScheduleDaysField
                        hours={draft.hours}
                        onChange={draft.setDay}
                        minutes={draft.minutes}
                        total={draft.total}
                        shift={draft.shift}
                        weeklyMaximum={weeklyMaximum}
                        errors={errors}
                    />
                    <ScheduleStartsField
                        starts={draft.starts}
                        onChange={draft.setStart}
                        errors={errors}
                    />
                    <SchedulePreview
                        minutes={draft.minutes}
                        total={draft.total}
                        weeklyMaximum={weeklyMaximum}
                    />
                    <InputError message={errors['employee_ids']} />
                </div>
            )}
        </FormDialog>
    );
}
