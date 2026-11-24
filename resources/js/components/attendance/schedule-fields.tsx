import ScheduleCopyField from '@/components/attendance/schedule-copy-field';
import ScheduleDaysTable from '@/components/attendance/schedule-days-table';
import SchedulePeopleInputs from '@/components/attendance/schedule-people-inputs';
import SchedulePreview from '@/components/attendance/schedule-preview';
import ScheduleRemoteField from '@/components/attendance/schedule-remote-field';
import ScheduleShiftTag from '@/components/attendance/schedule-shift-tag';
import ScheduleStartField from '@/components/attendance/schedule-start-field';
import ScheduleTemplates from '@/components/attendance/schedule-templates';
import ScheduleTimeline from '@/components/attendance/schedule-timeline';
import ScheduleValidityField from '@/components/attendance/schedule-validity-field';
import ScheduleWarnings from '@/components/attendance/schedule-warnings';
import ScheduleWeekInputs from '@/components/attendance/schedule-week-inputs';
import type { ScheduleDraft } from '@/hooks/use-schedule-draft';
import type { ScheduleTemplate } from '@/lib/attendance/schedule-templates';
import type { SchedulePerson, WeeklyMaximum } from '@/types';

/**
 * What the schedule sheet asks, in order: for one person what they have today, coming and before; the day it starts; then
 * the week (a template or someone else's, the start time, the week drawn with its total) and, folded, each day.
 */
export default function ScheduleFields({
    people,
    draft,
    templates,
    weeklyMaximum,
    today,
    errors,
}: {
    people: SchedulePerson[];
    draft: ScheduleDraft;
    templates: ScheduleTemplate[];
    weeklyMaximum: WeeklyMaximum;
    today: string;
    errors: Partial<Record<string, string>>;
}) {
    const [only] = people;

    return (
        <>
            <SchedulePeopleInputs people={people} />
            <ScheduleWeekInputs week={draft.week} mode={draft.mode} />
            {only !== undefined && people.length === 1 && (
                <ScheduleTimeline person={only} />
            )}
            <ScheduleValidityField today={today} error={errors['valid_from']} />
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
    );
}
