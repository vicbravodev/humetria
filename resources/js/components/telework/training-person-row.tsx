import { Badge } from '@/components/ui/badge';
import { standingTone, trainingLines } from '@/lib/telework/training';
import type { TrainingPerson } from '@/types';

/** A person of the roster with when their yearly training is due and since when they have not had one. */
export default function TrainingPersonRow({
    person,
}: {
    person: TrainingPerson;
}) {
    const lines = trainingLines(person);

    return (
        <li className="flex flex-wrap items-center justify-between gap-3 card px-[22px] py-4">
            <div className="flex min-w-0 flex-col">
                <p className="font-medium">{person.name}</p>
                <p className="text-caption text-muted-foreground">
                    {person.jobTitle} · {person.workCenter}
                </p>
            </div>
            <div className="flex flex-wrap items-center gap-3 text-meta text-muted-foreground">
                <span>{lines.last}</span>
                <span>{lines.due}</span>
                <Badge variant={standingTone(person.status)}>
                    {lines.label}
                </Badge>
            </div>
        </li>
    );
}
