import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PageHeader from '@/components/humetria/page-header';
import ActivityCard from '@/components/tenancy/activity-card';
import AttentionSection from '@/components/tenancy/attention-section';
import BilledCard from '@/components/tenancy/billed-card';
import DemoFunnelCard from '@/components/tenancy/demo-funnel-card';
import TodayFigures from '@/components/tenancy/today-figures';
import { attentionItems } from '@/lib/attention';
import { greeting } from '@/lib/overview-today';
import type { PlatformOverviewData } from '@/types';

/** «Resumen» (tablero Hoy): what needs the superadmin today first, then how the platform is doing. */
export default function Overview(data: PlatformOverviewData) {
    const { auth } = usePage().props;
    // The hour once, when the page opens: the greeting does not change under the reader.
    const [hour] = useState(() => new Date().getHours());
    const { companies: counts, inactive, overdue } = data;
    const attention = attentionItems({
        overdue,
        trialsEnding: counts.trial_ending,
        demosWaiting: data.demosWaiting,
        rejectedToday: data.rejectedToday,
        dormant: inactive,
    });

    return (
        <>
            <Head title="Resumen de la plataforma" />
            <div className="flex flex-col gap-6 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title={greeting(hour, auth.user.name)}
                    description={
                        attention.length === 0
                            ? 'Nada espera tu atención hoy.'
                            : 'Esto necesita tu atención hoy. Lo demás va bien.'
                    }
                />
                <TodayFigures
                    companies={counts}
                    overdue={overdue}
                    inactive={inactive}
                />
                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                    <div className="grid min-w-0 gap-6">
                        <AttentionSection items={attention} />
                        <BilledCard
                            revenueCents={data.revenueCents}
                            billedByMonth={data.billedByMonth}
                        />
                    </div>
                    <div className="grid min-w-0 gap-6">
                        <DemoFunnelCard demos={data.demos} />
                        <ActivityCard entries={data.activity} />
                    </div>
                </div>
            </div>
        </>
    );
}
