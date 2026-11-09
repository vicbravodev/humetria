import WeekRibbon from '@/components/humetria/week-ribbon';
import WeekRibbonLegend from '@/components/humetria/week-ribbon-legend';
import type { WeekView } from '@/lib/attendance/week';
import { ribbonDays, weekTotal } from '@/lib/attendance/week-text';

/** The week at a glance (board P4): the total, how the law pays it, and its seven bars; «Ver cada día» goes to the marks. */
export default function WeekTotal({ week }: { week: WeekView }) {
    const { total, parts } = weekTotal(week);

    return (
        <section
            aria-labelledby="week-total-title"
            className="flex flex-col gap-4 card p-5"
        >
            <div className="flex items-end justify-between gap-4">
                <div className="flex min-w-0 flex-col">
                    <h2
                        id="week-total-title"
                        className="text-base text-muted-foreground"
                    >
                        Total de la semana
                    </h2>
                    <p className="text-4xl leading-10 font-semibold tabular-nums">
                        {total}
                    </p>
                    <p className="text-base text-muted-foreground">{parts}</p>
                </div>
                <WeekRibbon days={ribbonDays(week.days)} size="md" labeled />
            </div>
            <WeekRibbonLegend />
            <a
                href="#week-days"
                className="inline-flex min-h-11 items-center self-start text-base font-medium text-primary"
            >
                Ver cada día
            </a>
        </section>
    );
}
