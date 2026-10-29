import { Badge } from '@/components/ui/badge';
import { EyeOff, LockKeyhole } from 'lucide-react';

const groups = [
    { name: 'Producción', answers: 46 },
    { name: 'Almacén', answers: 18 },
    { name: 'Administración', answers: 4 },
] as const;

const MINIMUM = 5;

/** A still picture of a campaign in progress: what RH sees, with the minimum per group at work. */
export default function HeroPreview() {
    return (
        <figure className="rounded-xl border bg-card p-6 shadow-raised sm:p-8">
            <figcaption className="sr-only">
                Ejemplo del avance de una campaña en Humetría
            </figcaption>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-medium">Planta Querétaro</p>
                    <p className="text-sm text-muted-foreground">
                        120 trabajadores · Guías I y III
                    </p>
                </div>
                <Badge>En curso</Badge>
            </div>

            <div className="mt-6">
                <div className="flex items-baseline justify-between text-sm">
                    <span className="text-muted-foreground">
                        Respuestas de la muestra
                    </span>
                    <span className="font-medium tabular-nums">68 de 92</span>
                </div>
                <div className="mt-2 h-2 rounded-full bg-muted">
                    <div className="h-2 w-[74%] rounded-full bg-primary" />
                </div>
            </div>

            <ul className="mt-6 divide-y border-y text-sm">
                {groups.map((group) => (
                    <li
                        key={group.name}
                        className="flex items-center justify-between gap-4 py-3"
                    >
                        <span>{group.name}</span>
                        {group.answers >= MINIMUM ? (
                            <span className="text-muted-foreground tabular-nums">
                                {group.answers} respuestas
                            </span>
                        ) : (
                            <span className="flex items-center gap-1.5 text-muted-foreground">
                                <EyeOff aria-hidden className="size-4" />
                                Se muestra con {MINIMUM} o más
                            </span>
                        )}
                    </li>
                ))}
            </ul>

            <p className="mt-5 flex items-center gap-2 text-sm text-muted-foreground">
                <LockKeyhole aria-hidden className="size-4 text-primary" />
                Nadie puede ver quién contestó qué.
            </p>
        </figure>
    );
}
