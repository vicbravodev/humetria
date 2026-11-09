import WeekPanelBar from '@/components/attendance/week-panel-bar';
import { duration } from '@/lib/attendance/week-text';
import type { AttendanceOverviewProps } from '@/types';

/** «Cómo van las N semanas»: how the people answered it and the hours of the week split in ordinary and extra. */
export default function WeekPanel({
    counts,
    hours,
}: Pick<AttendanceOverviewProps, 'counts' | 'hours'>) {
    const { confirmed, disputed, pending, all } = counts;
    const { ordinaryMinutes, overtimeMinutes } = hours;

    return (
        <aside
            aria-labelledby="semanas"
            className="flex min-w-0 flex-col gap-5 card p-[22px]"
        >
            <h2 id="semanas" className="text-lg leading-[26px] font-semibold">
                {all === 1
                    ? 'Cómo va la semana de 1 persona'
                    : `Cómo van las ${all} semanas`}
            </h2>
            <WeekPanelBar
                label={`${confirmed} confirmadas, ${disputed} objetadas, ${pending} sin confirmar`}
                parts={[
                    {
                        value: confirmed,
                        tone: 'bg-primary',
                        name: 'Confirmadas',
                        shown: `${confirmed}`,
                    },
                    {
                        value: disputed,
                        tone: 'bg-risk-alto',
                        name: 'Objetadas',
                        shown: `${disputed}`,
                    },
                    {
                        value: pending,
                        tone: 'bg-risk-medio',
                        name: 'Sin confirmar',
                        shown: `${pending}`,
                    },
                ]}
            />
            <hr />
            <div className="flex flex-col gap-2.5">
                <span className="text-caption font-medium text-muted-foreground">
                    Horas de la semana
                </span>
                <p className="flex items-baseline gap-2.5">
                    <span className="text-[32px] leading-10 font-semibold tracking-[-0.01em] tabular-nums">
                        {duration(ordinaryMinutes + overtimeMinutes)}
                    </span>
                    <span className="text-sm text-muted-foreground">
                        trabajadas
                    </span>
                </p>
                <WeekPanelBar
                    label={`${duration(ordinaryMinutes)} ordinarias y ${duration(overtimeMinutes)} extra`}
                    parts={[
                        {
                            value: ordinaryMinutes,
                            tone: 'bg-primary',
                            name: 'Ordinarias',
                            shown: duration(ordinaryMinutes),
                        },
                        {
                            value: overtimeMinutes,
                            tone: 'bg-risk-medio',
                            name: 'Extra',
                            shown: duration(overtimeMinutes),
                        },
                    ]}
                />
            </div>
        </aside>
    );
}
