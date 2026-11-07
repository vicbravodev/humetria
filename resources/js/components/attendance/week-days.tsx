import WeekDay from '@/components/attendance/week-day';
import type { WeekDay as Day } from '@/lib/attendance/week';

/** The seven days of the week, each with its marks. */
export default function WeekDays({ days }: { days: Day[] }) {
    return (
        <section
            aria-labelledby="week-days-title"
            className="flex flex-col gap-1 card p-5"
        >
            <h2
                id="week-days-title"
                className="text-xl leading-7 font-semibold"
            >
                Sus marcas
            </h2>
            <ol className="flex flex-col divide-y">
                {days.map((day) => (
                    <WeekDay key={day.date} day={day} />
                ))}
            </ol>
        </section>
    );
}
