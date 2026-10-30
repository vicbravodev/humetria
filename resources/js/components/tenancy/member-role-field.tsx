import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { RoleOption } from '@/types';

const HINTS: Record<string, string> = {
    hr: 'Administra centros de trabajo y empleados.',
    viewer: 'Solo consulta los centros de trabajo.',
};

/** The role an invited person gets: RH or Consulta, each with what it allows. */
export default function MemberRoleField({
    roles,
    defaultValue,
    error,
}: {
    roles: RoleOption[];
    defaultValue: string;
    error: string | undefined;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="member-role">Rol</Label>
            <Select name="role" defaultValue={defaultValue}>
                <SelectTrigger
                    id="member-role"
                    className="w-full"
                    aria-invalid={error === undefined ? undefined : true}
                    aria-describedby={
                        error === undefined ? undefined : 'member-role-error'
                    }
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {roles.map((role) => (
                        <SelectItem key={role.value} value={role.value}>
                            {role.label} · {HINTS[role.value]}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError id="member-role-error" role="alert" message={error} />
        </div>
    );
}
