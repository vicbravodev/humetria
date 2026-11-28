import StateDot from '@/components/operations/state-dot';
import type { Alert } from '@/lib/operations-health';

/** What needs someone now (tablero Salud): each service in trouble and the jobs that failed today. */
export default function AlertsCard({ alerts }: { alerts: Alert[] }) {
    return (
        <section className="card p-0">
            <header className="border-b px-6 py-4">
                <h2 className="text-base font-semibold">Alertas activas</h2>
            </header>
            {alerts.length === 0 ? (
                <p className="px-6 py-3.5 text-sm text-muted-foreground">
                    Ninguna: todo opera con normalidad.
                </p>
            ) : (
                <ul className="divide-y">
                    {alerts.map((alert) => (
                        <li
                            key={alert.title}
                            className="flex items-center gap-3 px-6 py-3.5"
                        >
                            <StateDot variant={alert.tone} />
                            <div className="min-w-0 flex-1">
                                <p className="font-medium">{alert.title}</p>
                                <p className="text-sm text-muted-foreground">
                                    {alert.detail}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
