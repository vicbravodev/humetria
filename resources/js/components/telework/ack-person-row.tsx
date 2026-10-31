import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { describeDate } from '@/lib/calendar-date';
import type { AckPerson } from '@/types';

/** One person asked to confirm: their name and job, how they stand, and a box to tick when RH records paper for them. */
export default function AckPersonRow({
    person,
    selectable,
    checked,
    disabled,
    onCheckedChange,
}: {
    person: AckPerson;
    selectable: boolean;
    checked: boolean;
    disabled: boolean;
    onCheckedChange: (checked: boolean) => void;
}) {
    const done = person.status === 'acknowledged';
    const tickable = selectable && !done;

    return (
        <li className="flex items-center gap-3 px-4 py-3">
            {tickable && (
                <Checkbox
                    id={`ack-${person.id}`}
                    aria-label={`Elegir a ${person.name}`}
                    checked={checked}
                    disabled={disabled}
                    onCheckedChange={(state) => onCheckedChange(state === true)}
                />
            )}
            <Label
                htmlFor={tickable ? `ack-${person.id}` : undefined}
                className="flex flex-1 flex-col items-start gap-0.5 font-normal"
            >
                <span className="font-medium">{person.name}</span>
                <span className="text-sm text-muted-foreground">
                    {person.jobTitle}
                </span>
            </Label>
            <Badge variant={done ? 'success' : 'warning'}>
                {done
                    ? `${person.channelLabel ?? 'Confirmó'} · ${describeDate(person.acknowledgedOn ?? '') ?? ''}`
                    : 'Pendiente'}
            </Badge>
        </li>
    );
}
