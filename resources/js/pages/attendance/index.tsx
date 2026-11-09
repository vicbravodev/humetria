import { Head, setLayoutProps } from '@inertiajs/react';
import CentersZones from '@/components/attendance/centers-zones';
import OverviewCards from '@/components/attendance/overview-cards';
import OverviewEmpty from '@/components/attendance/overview-empty';
import PeopleWeeks from '@/components/attendance/people-weeks';
import WeekSwitch from '@/components/attendance/week-switch';
import PageHeader from '@/components/humetria/page-header';
import { Badge } from '@/components/ui/badge';
import { weekTitle } from '@/lib/attendance/week-text';
import type { AttendanceOverviewProps } from '@/types';

/**
 * RH's overview of the registro de jornada (pantalla Eval-Activa: figures, tabs with counts and the people page by page):
 * one week of the company, or of one center, and its centers with their zones.
 */
export default function AttendanceIndex(props: AttendanceOverviewProps) {
    const { week, center, counts, centers } = props;
    setLayoutProps({
        breadcrumbs: [
            { title: 'Registro de jornada' },
            { title: 'Asistencia' },
        ],
    });
    const empty = counts.all === 0;

    return (
        <>
            <Head title="Asistencia" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption={center?.name ?? 'Todos tus centros'}
                    title="Asistencia"
                    status={
                        <Badge variant={week.over ? 'secondary' : 'default'}>
                            {week.over ? 'Semana cerrada' : 'Semana en curso'}
                        </Badge>
                    }
                    description={weekTitle(week.start, week.end)}
                >
                    <WeekSwitch
                        week={week}
                        filters={{ ...props.filters, center: center?.id ?? '' }}
                    />
                </PageHeader>
                {empty ? (
                    <OverviewEmpty hasCenters={centers.total > 0} />
                ) : (
                    <>
                        <OverviewCards {...props} />
                        <PeopleWeeks {...props} />
                    </>
                )}
                {centers.total > 0 && (
                    <CentersZones centers={centers} week={week.start} />
                )}
            </div>
        </>
    );
}
