import { Head } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import AlertsCard from '@/components/operations/alerts-card';
import FailedJobsCard from '@/components/operations/failed-jobs-card';
import HealthFigures from '@/components/operations/health-figures';
import SchedulerCard from '@/components/operations/scheduler-card';
import ServiceList from '@/components/operations/service-list';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { services, troubleLine, troubled } from '@/lib/operations';
import { activeAlerts, splitServices, withBars } from '@/lib/operations-health';
import type { HealthHours, SystemHealthData } from '@/types';

/** «Salud del sistema» (tablero Salud): what runs behind the platform, without opening the logs. Counts and states only. */
export default function Health(health: SystemHealthData & HealthHours) {
    const list = services(health);
    const issues = troubled(list);
    const { rows, scheduler } = splitServices(list);

    return (
        <>
            <Head title="Salud del sistema" />
            <div className="flex flex-col gap-6 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    caption="Plataforma"
                    title="Salud del sistema"
                    status={
                        <Badge variant={issues === 0 ? 'success' : 'warning'}>
                            {troubleLine(issues)}
                        </Badge>
                    }
                    description="Lo que corre detrás de la plataforma, sin abrir los logs. Solo conteos, estados y fallos; nunca datos de personas, respuestas ni mensajes."
                >
                    <Button variant="outline" asChild>
                        <a href="/horizon">Abrir Horizon</a>
                    </Button>
                    <Button variant="outline" asChild>
                        <a
                            href="https://us.posthog.com"
                            target="_blank"
                            rel="noreferrer"
                        >
                            Abrir PostHog
                        </a>
                    </Button>
                </PageHeader>
                <HealthFigures health={health} />
                <div className="flex flex-wrap items-start gap-6">
                    <div className="flex min-w-0 flex-[999_1_560px] flex-col gap-6">
                        <ServiceList
                            services={withBars(rows, health)}
                            limit={health.rejectionLimit}
                        />
                        <FailedJobsCard week={health.failedJobs.week} />
                    </div>
                    <div className="flex max-w-full min-w-0 flex-[1_1_320px] flex-col gap-6">
                        {scheduler !== undefined && (
                            <SchedulerCard scheduler={scheduler} />
                        )}
                        <AlertsCard
                            alerts={activeAlerts(list, health.failedJobs.today)}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}
