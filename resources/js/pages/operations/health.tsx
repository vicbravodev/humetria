import { Head } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import FailedJobsCard from '@/components/operations/failed-jobs-card';
import HealthFigures from '@/components/operations/health-figures';
import QueueList from '@/components/operations/queue-list';
import ServiceList from '@/components/operations/service-list';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { services, troubleLine, troubled } from '@/lib/operations';
import type { SystemHealthData } from '@/types';

/** «Salud del sistema»: what runs behind the platform, without opening the logs. Counts and states only. */
export default function Health(health: SystemHealthData) {
    const list = services(health);
    const issues = troubled(list);

    return (
        <>
            <Head title="Salud del sistema" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    caption="Plataforma"
                    title="Salud del sistema"
                    status={
                        <Badge variant={issues === 0 ? 'success' : 'warning'}>
                            {troubleLine(issues)}
                        </Badge>
                    }
                    description="Lo que corre detrás de la plataforma, sin abrir los logs. Solo conteos y estados; nunca datos de personas, respuestas ni mensajes."
                >
                    <Button variant="outline" asChild>
                        <a href="/horizon">Abrir Horizon</a>
                    </Button>
                </PageHeader>
                <HealthFigures health={health} />
                <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
                    <ServiceList services={list} />
                    <QueueList queues={health.queue.queues} />
                </div>
                <FailedJobsCard week={health.failedJobs.week} />
            </div>
        </>
    );
}
