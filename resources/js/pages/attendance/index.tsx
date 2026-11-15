import { Head, setLayoutProps } from '@inertiajs/react';
import CentersZones from '@/components/attendance/centers-zones';
import AttentionList from '@/components/attendance/attention-list';
import OverviewEmpty from '@/components/attendance/overview-empty';
import PeopleWeeks from '@/components/attendance/people-weeks';
import SetupBanner from '@/components/attendance/setup-banner';
import WeekPanel from '@/components/attendance/week-panel';
import WeekSwitch from '@/components/attendance/week-switch';
import PageHeader from '@/components/humetria/page-header';
import { Badge } from '@/components/ui/badge';
import { weekTitle } from '@/lib/attendance/week-text';
import type { AttendanceOverviewProps } from '@/types';

/**
 * RH's overview of the registro de jornada (board A1 of the Asistencia redesign): first what needs RH, then the week
 * of the company, or of one center, person by person, and its centers with their zones. While the registro is not ready,
 * a line takes RH to the puesta en marcha.
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
                        {/* The puesta en marcha and what needs RH share the column beside the week: no hole under either. */}
                        <div className="grid items-start gap-6 min-[1240px]:grid-cols-[minmax(0,1fr)_340px]">
                            <div className="flex min-w-0 flex-col gap-6">
                                <SetupBanner setup={props.setup} />
                                <AttentionList {...props} />
                            </div>
                            <WeekPanel {...props} />
                        </div>
                        <PeopleWeeks {...props} />
                    </>
                )}
                {centers.total > 0 && (
                    <CentersZones
                        centers={centers}
                        week={week.start}
                        requiresQr={props.setup.requiresQr}
                    />
                )}
            </div>
        </>
    );
}
