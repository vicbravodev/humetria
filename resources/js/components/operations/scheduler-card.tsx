import { Badge } from '@/components/ui/badge';
import type { Service } from '@/lib/operations';

/**
 * The scheduler (tablero Salud): its beat every five minutes, the only run the app keeps. A scheduled task that fails is
 * logged and alerts in PostHog; its runs are not kept here.
 */
export default function SchedulerCard({ scheduler }: { scheduler: Service }) {
    return (
        <section className="card p-0">
            <header className="border-b px-6 py-4">
                <h2 className="text-base font-semibold">
                    Programador de tareas
                </h2>
            </header>
            <div className="flex items-center gap-3 px-6 py-3.5">
                <div className="min-w-0 flex-1">
                    <p className="font-medium">Latido del programador</p>
                    <p className="text-sm text-muted-foreground">
                        {scheduler.detail}
                    </p>
                </div>
                <Badge variant={scheduler.state.variant}>
                    {scheduler.state.label}
                </Badge>
            </div>
            <p className="border-t px-6 py-3.5 text-sm text-muted-foreground">
                Si una tarea programada falla, queda en los logs y avisa en
                PostHog.
            </p>
        </section>
    );
}
