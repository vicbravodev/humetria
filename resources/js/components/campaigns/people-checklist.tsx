import { useState } from 'react';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { matchesPerson } from '@/lib/campaigns';
import type { RosterPerson } from '@/types';

/** The work center's active people, to tick who went through the event. Searchable by name or area. */
export default function PeopleChecklist({
    people,
    error,
}: {
    people: RosterPerson[];
    error: string | undefined;
}) {
    const [search, setSearch] = useState('');
    const [chosen, setChosen] = useState<string[]>([]);
    const shown = people.filter((person) =>
        matchesPerson({ ...person, inSample: false, guides: [] }, search),
    );
    const toggle = (id: string, checked: boolean) =>
        setChosen((current) =>
            checked
                ? [...current, id]
                : current.filter((other) => other !== id),
        );

    return (
        <fieldset
            className="flex flex-col gap-3"
            aria-describedby="trauma-people-error"
        >
            <legend className="mb-1 text-sm font-medium">
                Personas que lo vivieron o presenciaron ({chosen.length}{' '}
                elegidas)
            </legend>
            <Input
                type="search"
                aria-label="Buscar por nombre o área"
                placeholder="Buscar por nombre o área"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                className="max-w-sm"
            />
            {chosen.map((id) => (
                <input
                    key={id}
                    type="hidden"
                    name="employee_ids[]"
                    value={id}
                />
            ))}
            <ul className="flex max-h-96 flex-col divide-y overflow-y-auto rounded-lg border bg-card">
                {shown.map((person) => (
                    <li
                        key={person.id}
                        className="flex items-center gap-3 px-4 py-3"
                    >
                        <Checkbox
                            id={`person-${person.id}`}
                            checked={chosen.includes(person.id)}
                            onCheckedChange={(checked) =>
                                toggle(person.id, checked === true)
                            }
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
                ))}
            </ul>
            <InputError id="trauma-people-error" role="alert" message={error} />
        </fieldset>
    );
}
