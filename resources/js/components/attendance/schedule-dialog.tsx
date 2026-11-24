import { useState } from 'react';
import ScheduleFields from '@/components/attendance/schedule-fields';
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
    today,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    people: SchedulePerson[];
    weeklyMaximum: WeeklyMaximum;
    today: string;
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
                <ScheduleFields
                    people={people}
                    draft={draft}
                    templates={templates}
                    weeklyMaximum={weeklyMaximum}
                    today={today}
                    errors={errors}
                />
            )}
        </FormSheet>
    );
}
