import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index as policies } from '@/routes/tenant/vacations/policies';

const STEPS = [
    {
        title: 'Revisa tu política',
        text: 'Por defecto sigue la ley. Si das más días, crea tu política.',
    },
    {
        title: 'Carga los saldos iniciales',
        text: 'Los días que cada persona ya tiene, por archivo.',
    },
    {
        title: 'Comparte el portal',
        text: 'Cada persona pide sus vacaciones y su jefe las responde.',
    },
];

/** What an empty company does first: three numbered steps and a single button. */
export default function FirstSteps() {
    const tenantSlug = useTenantSlug();

    return (
        <section
            aria-labelledby="leave-first"
            className="flex flex-col gap-6 card p-6 md:p-8"
        >
            <div className="flex flex-col gap-1">
                <h2 id="leave-first" className="text-xl font-semibold">
                    Vacaciones empieza aquí
                </h2>
                <p className="text-muted-foreground">
                    Tres pasos y tu gente pide sus días desde el celular.
                </p>
            </div>
            <ol className="grid gap-4 md:grid-cols-3">
                {STEPS.map((step, index) => (
                    <li key={step.title} className="flex gap-3">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent text-sm font-semibold text-primary">
                            {index + 1}
                        </span>
                        <p className="flex flex-col gap-0.5">
                            <span className="font-semibold">{step.title}</span>
                            <span className="text-meta text-muted-foreground">
                                {step.text}
                            </span>
                        </p>
                    </li>
                ))}
            </ol>
            <Button asChild className="w-fit max-sm:h-11 max-sm:w-full">
                <Link href={policies.url(tenantSlug)}>
                    Empezar por la política
                </Link>
            </Button>
        </section>
    );
}
