import { Info, X } from 'lucide-react';
import { useDismissed } from '@/hooks/use-dismissed';

/**
 * What the chart is for, in one line, for whoever opens it without knowing: it can be closed and stays closed in this
 * browser.
 */
export default function OrgIntroNote() {
    const [dismissed, dismiss] = useDismissed('org-chart-intro');

    if (dismissed) {
        return null;
    }

    return (
        <aside
            aria-label="Para qué sirve el organigrama"
            className="flex items-center gap-3 card px-4 py-3 print:hidden"
        >
            <Info aria-hidden className="size-5 shrink-0 text-primary" />
            <p className="min-w-0 flex-1 text-meta">
                De aquí salen quién aprueba las vacaciones de cada persona y los
                resultados de Clima y Pulso por jefe.
            </p>
            <button
                type="button"
                aria-label="Cerrar esta nota"
                onClick={dismiss}
                className="relative -m-1 flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors duration-150 before:absolute before:-inset-1.5 hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <X aria-hidden className="size-4" />
            </button>
        </aside>
    );
}
