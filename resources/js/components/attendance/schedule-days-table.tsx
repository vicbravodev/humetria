import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import ScheduleDayRow from '@/components/attendance/schedule-day-row';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import type { ScheduleDraft } from '@/hooks/use-schedule-draft';
import { WEEKDAYS } from '@/lib/attendance/schedule';
import { isRest } from '@/lib/attendance/schedule-draft';

/**
 * «Personalizar días», folded: the seven days in one table (day, hours, start), for a week no template gives. It starts
 * open when the saved week is none of them, or when the form came back with an error in a day.
 */
export default function ScheduleDaysTable({
    draft,
    defaultOpen,
    errors,
}: {
    draft: ScheduleDraft;
    defaultOpen: boolean;
    errors: Partial<Record<string, string>>;
}) {
    const [open, setOpen] = useState(defaultOpen);
    const dayError = Object.keys(errors).some(
        (key) => key.startsWith('minutes') || key.startsWith('starts'),
    );

    return (
        <Collapsible
            open={open || dayError}
            onOpenChange={setOpen}
            className="group/days rounded-lg border bg-card"
        >
            <CollapsibleTrigger className="flex min-h-11 w-full items-center justify-between gap-2 rounded-lg px-4 py-2.5 text-left font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                Personalizar días
                <ChevronDown
                    aria-hidden
                    className="size-4 shrink-0 text-muted-foreground transition-transform duration-200 group-data-[state=open]/days:rotate-180"
                />
            </CollapsibleTrigger>
            <CollapsibleContent className="px-4 pb-3">
                <div
                    aria-hidden
                    className="grid grid-cols-[minmax(0,1fr)_4rem_7.5rem] gap-x-2 pb-1 text-caption text-muted-foreground sm:grid-cols-[minmax(0,1fr)_4.5rem_7.5rem] sm:gap-x-3"
                >
                    <span>Día</span>
                    <span>Horas</span>
                    <span>Entrada</span>
                </div>
                {WEEKDAYS.map(({ day, name }) => (
                    <ScheduleDayRow
                        key={day}
                        day={day}
                        name={name}
                        hours={draft.week.hours[day] ?? ''}
                        start={draft.week.starts[day] ?? ''}
                        rest={isRest(draft.week, day)}
                        invalid={{
                            hours: errors[`minutes.${day}`] !== undefined,
                            start: errors[`starts.${day}`] !== undefined,
                        }}
                        onHours={(text) => draft.setHours(day, text)}
                        onStart={(time) => draft.setDayStart(day, time)}
                        onRest={(rest) => draft.setRest(day, rest)}
                    />
                ))}
            </CollapsibleContent>
        </Collapsible>
    );
}
