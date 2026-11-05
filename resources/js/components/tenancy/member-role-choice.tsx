import InputError from '@/components/humetria/input-error';
import type { RoleOption } from '@/types';

const HINTS: Record<string, string> = {
    hr: 'Avanza tareas y documentos. También ve canalización y compensación.',
    viewer: 'Ve rutas, resultados agregados y el expediente de NOM-037, sin cambiar nada. Para dirección.',
    committee:
        'Valida lugares de teletrabajo y firma evaluaciones de Ley Silla de los centros donde participa.',
    complaints:
        'Solo el canal de quejas: atiende los casos que le designen. Para un asesor externo de confianza.',
};

/** What the invited person will do, as option cards (board V3-Roles); «Ayuda con las rutas» is chosen to begin with. */
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
            <legend className="mb-2 font-medium">Qué hará en Humetría</legend>
            <div className="grid gap-2.5">
                {roles.map((role) => (
                    <label
                        key={role.value}
                        className="flex min-h-14 cursor-pointer items-start gap-3 rounded-lg border border-input bg-card px-4 py-3 transition-colors duration-100 ease-out hover:bg-muted has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[15px] has-checked:py-[11px] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring"
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
            <InputError id="member-role-error" message={error} />
        </fieldset>
    );
}
