import { Badge } from '@/components/ui/badge';
import { scheduleDays } from '@/lib/attendance/schedule';
import { startLabel } from '@/lib/attendance/schedule-starts';
import { validityTimeline } from '@/lib/attendance/schedule-validity';
import type { ValidityEntry } from '@/lib/attendance/schedule-validity';
import type { SchedulePerson } from '@/types';

const KIND: Record<
    ValidityEntry['kind'],
    { label: string; variant: 'default' | 'warning' | 'secondary' }
> = {
    current: { label: 'Vigente', variant: 'default' },
    coming: { label: 'Próximo', variant: 'warning' },
    past: { label: 'Anterior', variant: 'secondary' },
};

/**
 * What applies to the person today, what is coming and what applied before, each from its day: a schedule is never
 * erased, so the history says what each day was counted with. Nothing for someone with no schedule yet.
 */
export default function ScheduleTimeline({
    person,
}: {
    person: SchedulePerson;
}) {
    const entries = validityTimeline(
        person.schedule,
        person.upcoming,
        person.history,
    );

    if (entries.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="schedule-timeline" className="grid gap-2">
            <h3 id="schedule-timeline" className="text-sm font-medium">
                Horarios de {person.name}
            </h3>
            <ol className="grid gap-2">
                {entries.map(({ kind, since, schedule }) => (
                    <li
                        key={`${kind}-${schedule.validFrom ?? 'always'}`}
                        className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-lg border bg-card px-3 py-2"
                    >
                        <div className="flex min-w-0 flex-col">
                            <span className="font-medium">{since}</span>
                            <span className="text-caption text-muted-foreground">
                                {[
                                    scheduleDays(schedule.minutes),
                                    startLabel(
                                        schedule.minutes,
                                        schedule.starts,
                                    ),
                                ]
                                    .filter((part) => part !== null)
                                    .join(' · ')}
                            </span>
                        </div>
                        <Badge variant={KIND[kind].variant}>
                            {KIND[kind].label}
                        </Badge>
                    </li>
                ))}
            </ol>
        </section>
    );
}
