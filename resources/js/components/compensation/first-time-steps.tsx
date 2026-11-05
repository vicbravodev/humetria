import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { create as importRoster } from '@/routes/tenant/employees/import';

const STEPS = [
    {
        title: 'Carga tu padrón con categoría y nivel',
        text: 'Un CSV de tu sistema de nómina. Te damos la plantilla. ≈ 5 min',
    },
    {
        title: 'Captura el tabulador',
        text: 'El salario diario de cada categoría y nivel, como viene en el contrato.',
    },
    {
        title: 'Revisa las prestaciones',
        text: 'Ya cargamos las de ley. Solo ajusta las que mejora tu contrato.',
    },
];

/** «Antes de costear, 3 pasos» (board V4-Comp-Inicio, primera vez): the roster first, with its one action. */
export default function FirstTimeSteps() {
    const tenantSlug = useTenantSlug();

    return (
        <section aria-labelledby="first-steps" className="grid gap-5 card p-6">
            <div className="grid gap-1">
                <h2
                    id="first-steps"
                    className="text-xl leading-7 font-semibold"
                >
                    Antes de costear, 3 pasos
                </h2>
                <p className="text-muted-foreground">
                    Se hacen una vez. Después, cada propuesta se arma en un par
                    de minutos.
                </p>
            </div>
            <ol className="grid gap-3">
                {STEPS.map((step, index) => (
                    <li
                        key={step.title}
                        className="flex flex-wrap items-start gap-x-4 gap-y-3 rounded-lg border px-4 py-4"
                    >
                        <span
                            aria-hidden
                            className={
                                index === 0
                                    ? 'grid size-8 shrink-0 place-items-center rounded-full bg-primary font-semibold text-primary-foreground'
                                    : 'grid size-8 shrink-0 place-items-center rounded-full bg-muted font-semibold text-muted-foreground'
                            }
                        >
                            {index + 1}
                        </span>
                        <span className="grid min-w-0 flex-1 gap-0.5">
                            <span className="font-semibold">{step.title}</span>
                            <span className="text-sm text-muted-foreground">
                                {step.text}
                            </span>
                        </span>
                        {index === 0 && (
                            <Button asChild>
                                <Link href={importRoster(tenantSlug)}>
                                    Cargar el padrón
                                </Link>
                            </Button>
                        )}
                    </li>
                ))}
            </ol>
        </section>
    );
}
