import InputError from '@/components/input-error';
import type { RoleOption } from '@/types';

const HINTS: Record<string, string> = {
    hr: 'Opera las evaluaciones del día a día.',
    viewer: 'Solo consulta, sin cambiar nada.',
    committee:
        'Solo ve la NOM-037: revisa y valida los lugares de teletrabajo.',
};

/** The role an invited person gets, as option cards (pantallas: Usuarios-Invitar); RH is chosen to begin with. */
export default function MemberRoleChoice({
    roles,
    error,
}: {
    roles: RoleOption[];
    error: string | undefined;
}) {
    return (
        <fieldset
            aria-describedby={
                error === undefined ? undefined : 'member-role-error'
            }
        >
            <legend className="mb-2 text-sm font-medium">Rol</legend>
            <div className="flex flex-wrap items-stretch gap-2.5">
                {roles.map((role) => (
                    <label
                        key={role.value}
                        className="flex min-h-14 flex-[1_1_200px] cursor-pointer items-start gap-3 rounded-lg border border-input bg-card px-4 py-3 transition-colors duration-100 ease-out hover:bg-muted has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[15px] has-checked:py-[11px] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring"
                    >
                        <input
                            type="radio"
                            name="role"
                            value={role.value}
                            defaultChecked={role.value === 'hr'}
                            className="mt-0.5 size-5 shrink-0 accent-primary"
                        />
                        <span className="flex flex-col">
                            <span className="font-medium">{role.label}</span>
                            <span className="text-sm text-muted-foreground">
                                {HINTS[role.value]}
                            </span>
                        </span>
                    </label>
                ))}
            </div>
            <InputError id="member-role-error" role="alert" message={error} />
        </fieldset>
    );
}
