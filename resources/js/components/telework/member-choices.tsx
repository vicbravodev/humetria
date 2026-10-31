import { Users } from 'lucide-react';
import SettingRow from '@/components/humetria/setting-row';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { CommitteePerson } from '@/types';

/** The people who can sit on the Comisión, to tick (at least one). */
export default function MemberChoices({
    candidates,
    error,
    centerError,
}: {
    candidates: CommitteePerson[];
    error: string | undefined;
    centerError: string | undefined;
}) {
    return (
        <SettingRow
            icon={Users}
            title="Integrantes"
            description="Quienes revisan los lugares de teletrabajo de este centro. Elige al menos una persona."
        >
            <ul className="flex flex-col gap-2">
                {candidates.map((person) => (
                    <li key={person.id} className="flex items-center gap-3">
                        <Checkbox
                            id={`member-${person.id}`}
                            name="member_ids[]"
                            value={String(person.id)}
                        />
                        <Label htmlFor={`member-${person.id}`}>
                            {person.name}
                            <span className="font-normal text-muted-foreground">
                                {' '}
                                · {person.role}
                            </span>
                        </Label>
                    </li>
                ))}
            </ul>
            <InputError role="alert" message={error} />
            <InputError role="alert" message={centerError} />
        </SettingRow>
    );
}
