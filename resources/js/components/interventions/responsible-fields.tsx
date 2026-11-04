import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ProgramMember } from '@/types';

type Errors = Partial<Record<string, string>>;

/**
 * Who answers for it (num. 8.4 f): a member of the company, who is named by their own name and receives the reminders, or a
 * name for someone from outside. Left empty, it is the Programa's responsible.
 */
export default function ResponsibleFields({
    members,
    userId,
    name,
    errors,
}: {
    members: ProgramMember[];
    userId: number | null;
    name: string;
    errors: Errors;
}) {
    const options = members.map((member) => ({
        value: String(member.id),
        label: member.name,
    }));

    return (
        <fieldset className="grid gap-4">
            <legend className="mb-1 text-sm font-medium">Responsable</legend>
            <div className="grid gap-2">
                <Label htmlFor="responsible-member">
                    Persona de la empresa
                </Label>
                <Combobox
                    id="responsible-member"
                    name="responsible_user_id"
                    source={{ options }}
                    defaultValue={options.find(
                        (option) => option.value === String(userId),
                    )}
                    placeholder="Elige a la persona"
                />
                <InputError message={errors['responsible_user_id']} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="responsible-name">
                    O el nombre de alguien externo
                </Label>
                <Input
                    id="responsible-name"
                    name="responsible_name"
                    defaultValue={userId === null ? name : ''}
                    aria-invalid={errors['responsible_name'] !== undefined}
                />
                <InputError message={errors['responsible_name']} />
            </div>
        </fieldset>
    );
}
