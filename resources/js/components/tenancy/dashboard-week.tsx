import type { WeekItem } from '@/types';

/** "Esta semana": what falls due in the next seven days. */
export default function DashboardWeek({ items }: { items: WeekItem[] }) {
    return (
        <section
            aria-labelledby="week"
            className="flex flex-col gap-2.5 card p-[22px]"
        >
            <h2 id="week" className="text-sm font-semibold">
                Esta semana
            </h2>
            {items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Nada vence en los próximos 7 días.
                </p>
            ) : (
                <ul className="flex flex-col gap-2.5">
                    {items.map((item) => (
                        <li
                            key={`${item.day}-${item.text}`}
                            className="text-sm"
                        >
                            <b className="font-semibold">{item.day}</b> ·{' '}
                            {item.text}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
