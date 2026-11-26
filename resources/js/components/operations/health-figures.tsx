import FigureCard from '@/components/operations/figure-card';
import { count, deliveredShare, trafficOf } from '@/lib/operations';
import type { ChannelTraffic, SystemHealthData } from '@/types';

function caption(traffic: ChannelTraffic | undefined): string {
    return `${deliveredShare(traffic)} entregados · ${count(traffic?.rejected ?? 0)} rechazados`;
}

/** The day in three figures (the design system allows three a screen): what failed, and the mail and WhatsApp that went out. What waits in the queues is in «Servicios». */
export default function HealthFigures({
    health,
}: {
    health: SystemHealthData;
}) {
    const mail = trafficOf(health.today, 'mail');
    const whatsApp = trafficOf(health.today, 'whatsapp');

    return (
        <div className="grid gap-4 sm:grid-cols-3">
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
