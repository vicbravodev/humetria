import { Check, ShieldCheck } from 'lucide-react';

const ROLES = [
    {
        name: 'Dueño',
        summary: 'Todo, incluido quién entra y con qué rol.',
        can: [
            'Usuarios y roles',
            'Centros y empleados',
            'Evaluaciones y resultados',
            'Canalización Guía I',
        ],
    },
    {
        name: 'RH',
        summary: 'Opera las evaluaciones del día a día.',
        can: [
            'Centros y empleados',
            'Evaluaciones y resultados',
            'Canalización Guía I',
        ],
    },
    {
        name: 'Consulta',
        summary: 'Solo consulta, sin cambiar nada.',
        can: ['Centros de trabajo', 'Avance y resultados'],
    },
];

/** What each company role allows, under the people with access (pantallas: Usuarios). */
export default function RoleCapabilities() {
    return (
        <section
            aria-labelledby="role-capabilities"
            className="overflow-hidden rounded-xl border bg-card"
        >
            <div className="flex items-center gap-2.5 border-b px-[18px] py-3.5">
                <ShieldCheck
                    aria-hidden
                    className="size-[18px] text-muted-foreground"
                />
                <h2 id="role-capabilities" className="text-sm font-semibold">
                    Qué puede hacer cada rol
                </h2>
            </div>
            <div className="-ml-px grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))]">
                {ROLES.map((role) => (
                    <div
                        key={role.name}
                        className="flex flex-col gap-2 border-l px-[18px] py-4"
                    >
                        <h3 className="font-semibold">{role.name}</h3>
                        <p className="text-[13px] leading-[18px] text-muted-foreground">
                            {role.summary}
                        </p>
                        <ul className="mt-1 flex flex-col gap-1">
                            {role.can.map((item) => (
                                <li
                                    key={item}
                                    className="flex items-center gap-1.5 text-[13px] leading-[18px]"
                                >
                                    <Check
                                        aria-hidden
                                        strokeWidth={2.25}
                                        className="size-3.5 text-primary"
                                    />
                                    {item}
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </div>
        </section>
    );
}
