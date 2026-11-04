import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import type { PolicyAckPerson } from '@/types';

const ROW = 'flex items-center gap-3 px-4 py-3';

/**
 * A person of the follow-up: their name and job, where their confirmation stands and, while pending, a box to tick. The
 * whole row is the label of the box, so a finger finds it.
 */
export default function AckRow({
    person,
    checked,
    disabled,
    onCheckedChange,
}: {
    person: PolicyAckPerson;
    checked: boolean;
    disabled: boolean;
    onCheckedChange: (checked: boolean) => void;
}) {
    const pending = person.state === 'pending';
    const body: ReactNode = (
        <>
            <div className="flex min-w-0 flex-1 flex-col">
                <span className="truncate font-medium">{person.name}</span>
                <span className="truncate text-meta text-muted-foreground">
                    {person.jobTitle}
                    {pending &&
                        !person.hasEmail &&
                        ' · Sin correo: regístrelo en papel'}
                </span>
            </div>
            {pending ? (
                <Badge variant="outline">Pendiente</Badge>
            ) : (
                <div className="flex flex-col items-end">
                    <Badge variant="success">Confirmó</Badge>
                    <span className="text-caption text-muted-foreground">
                        {person.channelLabel} · {person.on}
                    </span>
                </div>
            )}
        </>
    );

    return pending ? (
        <li>
            <label className={`${ROW} cursor-pointer hover:bg-muted`}>
                <Checkbox
                    aria-label={`Elegir a ${person.name}`}
                    checked={checked}
                    disabled={disabled}
                    onCheckedChange={(value) => onCheckedChange(value === true)}
                />
                {body}
            </label>
        </li>
    ) : (
        <li className={ROW}>
            <span aria-hidden className="size-5 shrink-0" />
            {body}
        </li>
    );
}
