import FigureCard from '@/components/operations/figure-card';
import { count, deliveredShare, trafficOf } from '@/lib/operations';
import type { ChannelTraffic, SystemHealthData } from '@/types';

function caption(traffic: ChannelTraffic | undefined): string {
    return `${deliveredShare(traffic)} entregados · ${count(traffic?.rejected ?? 0)} rechazados`;
}

/** The day in four figures: what waits in the queues, what failed, and the mail and WhatsApp that went out. */
export default function HealthFigures({
    health,
}: {
    health: SystemHealthData;
}) {
    const mail = trafficOf(health.today, 'mail');
    const whatsApp = trafficOf(health.today, 'whatsapp');

    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <FigureCard
                title="En cola"
                figure={count(health.queue.waiting)}
                caption="Trabajos esperando en todas las colas."
            />
            <FigureCard
                title="Fallidos hoy"
                figure={count(health.failedJobs.today)}
                caption="Trabajos que fallaron sin más reintentos."
            />
            <FigureCard
                title="Correos hoy"
                figure={count(mail?.total ?? 0)}
                caption={caption(mail)}
            />
            <FigureCard
                title="WhatsApp hoy"
                figure={count(whatsApp?.total ?? 0)}
                caption={caption(whatsApp)}
            />
        </div>
    );
}
