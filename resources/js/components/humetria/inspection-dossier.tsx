import { Link } from '@inertiajs/react';
import { FileDown } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const OPTIONS = [
    {
        value: 'all',
        title: 'Completo',
        text: 'Lo que tienes y lo que falta, marcado como pendiente.',
    },
    {
        value: 'ready',
        title: 'Solo lo que tienes',
        text: 'Para entregar al inspector.',
    },
] as const;

/** «Descargar expediente» (board V3-Inspeccion): the printable dossier, whole or with only what the center can show. */
export default function InspectionDossier({ href }: { href: string }) {
    const [choice, setChoice] = useState<'all' | 'ready'>('all');

    return (
        <aside
            aria-labelledby="dossier"
            className="flex flex-col gap-4 self-start card p-6"
        >
            <h2 id="dossier" className="text-lg font-semibold">
                Descargar expediente
            </h2>
            <p className="text-sm text-muted-foreground">
                Ordenado como la tabla de inspección, con la evidencia de cada
                renglón y su fecha. Lo imprimes o lo guardas en PDF desde tu
                navegador.
            </p>
            <fieldset className="flex flex-col gap-2">
                <legend className="mb-1 text-sm font-medium">
                    Qué incluye
                </legend>
                {OPTIONS.map((option) => (
                    <label
                        key={option.value}
                        className={cn(
                            'flex cursor-pointer items-start gap-3 rounded-lg border border-input bg-card px-4 py-3 hover:bg-muted',
                            'has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[15px] has-checked:py-[11px]',
                            'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                        )}
                    >
                        <input
                            type="radio"
                            name="dossier-content"
                            value={option.value}
                            checked={choice === option.value}
                            onChange={() => setChoice(option.value)}
                            className="mt-1 size-4 shrink-0 accent-primary"
                        />
                        <span>
                            <span className="block font-medium">
                                {option.title}
                            </span>
                            <span className="block text-sm text-muted-foreground">
                                {option.text}
                            </span>
                        </span>
                    </label>
                ))}
            </fieldset>
            <Button asChild>
                <Link href={choice === 'ready' ? `${href}?ready=1` : href}>
                    <FileDown aria-hidden />
                    Abrir expediente
                </Link>
            </Button>
            <p className="text-sm text-muted-foreground">
                Es un orden de lo registrado en Humetría, no una constancia de
                cumplimiento ante la autoridad.
            </p>
        </aside>
    );
}
