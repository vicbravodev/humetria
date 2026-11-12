import { X } from 'lucide-react';
import { useDismissed } from '@/hooks/use-dismissed';

const STEPS = [
    'Tus empleados ya están cargados',
    'Elige el jefe directo de cada persona (o súbelo en el CSV)',
    'Listo: el árbol y las aprobaciones se actualizan solos',
];

/** «Cómo se completa»: the three steps to a complete reporting line; it can be closed and stays closed in this browser. */
export default function OrgQualityHelp() {
    const [dismissed, dismiss] = useDismissed('org-chart-list-help');

    if (dismissed) {
        return null;
    }

    return (
        <section
            aria-labelledby="org-quality-help"
            className="relative rounded-xl border bg-card p-5 print:hidden"
        >
            <h2 id="org-quality-help" className="text-base font-semibold">
                Cómo se completa
            </h2>
            <ol className="mt-3 grid gap-3 md:grid-cols-3">
                {STEPS.map((step, index) => (
                    <li key={step} className="flex items-start gap-3 text-sm">
                        <span
                            aria-hidden
                            className="flex size-7 shrink-0 items-center justify-center rounded-full bg-accent text-sm font-semibold text-primary"
                        >
                            {index + 1}
                        </span>
                        <span className="pt-1">{step}</span>
                    </li>
                ))}
            </ol>
            <button
                type="button"
                aria-label="Cerrar esta ayuda"
                onClick={dismiss}
                className="absolute top-3 right-3 flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors duration-150 before:absolute before:-inset-1.5 hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <X aria-hidden className="size-4" />
            </button>
        </section>
    );
}
