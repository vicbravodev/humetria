import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { RosterPerson } from '@/types';

/** One person to tick: their name, area and whether they have an email. */
export default function PersonCheckRow({
    person,
    checked,
    onCheckedChange,
}: {
    person: RosterPerson;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <li className="flex items-center gap-3 px-4 py-3">
            <Checkbox
                id={`person-${person.id}`}
                checked={checked}
                onCheckedChange={(state) => onCheckedChange(state === true)}
            />
            <Label
                htmlFor={`person-${person.id}`}
                className="flex flex-1 flex-col items-start gap-0.5 font-normal"
            >
                <span className="font-medium">{person.name}</span>
                <span className="text-sm text-muted-foreground">
                    {person.area}
                    {!person.reachable && ' · Sin correo'}
                </span>
            </Label>
        </li>
    );
}
