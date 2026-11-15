import { Link } from '@inertiajs/react';
import { Hourglass, Lock } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { useEngagementRoutes } from '@/hooks/use-engagement-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';

/**
 * Results that cannot show yet: a survey still open (each new answer would tell what its author said), or one that
 * closed with fewer than 5 answers in all. Never how many are missing; the way to its progress.
 */
export default function ResultsWaiting({
    surveyId,
    open,
}: {
    surveyId: string;
    open: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const routes = useEngagementRoutes();

    return (
        <EmptyState
            icon={open ? Hourglass : Lock}
            title={
                open
                    ? 'Los resultados se abren al cerrar la encuesta'
                    : 'Todavía no hay suficientes respuestas para mostrar resultados'
            }
            action={
                <Button asChild>
                    <Link href={routes.show.url([tenantSlug, surveyId])}>
                        Ver avance
                    </Link>
                </Button>
            }
        >
            {open
                ? 'Mientras sigue abierta, cada respuesta nueva diría qué contestó quien acaba de contestar. Al cerrar se calculan una sola vez.'
                : 'Se muestran a partir de 5 respuestas, para proteger a quien contestó.'}
        </EmptyState>
    );
}
