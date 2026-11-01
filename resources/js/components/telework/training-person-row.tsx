import { Badge } from '@/components/ui/badge';
import { describeDate } from '@/lib/calendar-date';
import { standingTone } from '@/lib/telework/training';
import type { TrainingPerson } from '@/types';

/** A person of the roster with when their yearly training is due and since when they have not had one. */
export default function TrainingPersonRow({
    person,
}: {
    person: TrainingPerson;
}) {
    return (
        <li className="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card px-[22px] py-4">
            <div className="flex min-w-0 flex-col">
                <p className="font-medium">{person.name}</p>
                <p className="text-[13px] leading-[18px] text-muted-foreground">
                    {person.jobTitle} · {person.workCenter}
                </p>
            </div>
            <div className="flex flex-wrap items-center gap-3 text-[15px] text-muted-foreground">
                <span>
                    {person.lastTrainingOn === null
                        ? 'Sin capacitación'
                        : `Última: ${describeDate(person.lastTrainingOn) ?? person.lastTrainingOn}`}
                </span>
                <span>
                    Vence el {describeDate(person.dueOn) ?? person.dueOn}
                </span>
                <Badge variant={standingTone(person.status)}>
                    {person.statusLabel}
                </Badge>
            </div>
        </li>
    );
}
