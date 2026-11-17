import { useState } from 'react';
import ScheduleCopyField from '@/components/attendance/schedule-copy-field';
import ScheduleDaysTable from '@/components/attendance/schedule-days-table';
import SchedulePeopleInputs from '@/components/attendance/schedule-people-inputs';
import SchedulePreview from '@/components/attendance/schedule-preview';
import ScheduleRemoteField from '@/components/attendance/schedule-remote-field';
import ScheduleShiftTag from '@/components/attendance/schedule-shift-tag';
import ScheduleStartField from '@/components/attendance/schedule-start-field';
import ScheduleTemplates from '@/components/attendance/schedule-templates';
import ScheduleWarnings from '@/components/attendance/schedule-warnings';
import ScheduleWeekInputs from '@/components/attendance/schedule-week-inputs';
import FormSheet from '@/components/humetria/form-sheet';
import { useScheduleDraft } from '@/hooks/use-schedule-draft';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { peopleNames, replacingText } from '@/lib/attendance/schedule';
import { scheduleTemplates } from '@/lib/attendance/schedule-templates';
import { bulk, update } from '@/routes/tenant/attendance/schedules';
import type { SchedulePerson, WeeklyMaximum } from '@/types';

/**
 * RH fixes the agreed schedule of one person, or the same one for everyone chosen, in one screen of two levels: on top
 * what almost always is enough (a template or someone else's week, the one start time, the week drawn with its total);
 * below, folded, each day. The shift follows from the hours; working away is the exception. For several, it says who
 * loses the schedule they had.
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
    const [templates] = useState(() => scheduleTemplates(weeklyMaximum));
    const draft = useScheduleDraft(
        people.length === 1 ? (only?.schedule ?? null) : null,
        templates,
    );
    const names = peopleNames(people.map((person) => person.name));

    return (
        <FormSheet
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
                    : `${names}. El mismo horario para todas: con él se cuentan sus horas extra y sus días de descanso. ${replacingText(people) ?? ''}`
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
                <>
                    <SchedulePeopleInputs people={people} />
                    <ScheduleWeekInputs week={draft.week} mode={draft.mode} />
                    <ScheduleTemplates
                        templates={templates}
                        chosen={draft.template}
                        onPick={draft.apply}
                    />
                    <ScheduleCopyField onCopy={draft.copy} />
                    <ScheduleStartField
                        start={draft.start}
                        differs={draft.startsDiffer}
                        onChange={draft.setStart}
                    />
                    <div className="grid gap-3">
                        <SchedulePreview
                            minutes={draft.minutes}
                            total={draft.total}
                            weeklyMaximum={weeklyMaximum}
                        />
                        <ScheduleShiftTag
                            shift={draft.shift}
                            onChange={draft.setShift}
                            error={errors['shift_type']}
                        />
                        <ScheduleWarnings
                            minutes={draft.minutes}
                            total={draft.total}
                            shift={draft.shift}
                            weeklyMaximum={weeklyMaximum}
                            errors={errors}
                        />
                    </div>
                    <ScheduleDaysTable
                        draft={draft}
                        defaultOpen={draft.customized}
                        errors={errors}
                    />
                    <ScheduleRemoteField
                        remote={draft.mode === 'remote'}
                        onChange={(remote) =>
                            draft.setMode(remote ? 'remote' : 'onsite')
                        }
                    />
                </>
            )}
        </FormSheet>
    );
}
