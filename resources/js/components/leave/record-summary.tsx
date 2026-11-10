import { CalendarClock, Hourglass, ScrollText } from 'lucide-react';
import { countOf } from '@/lib/plural';

/** The three numbers RH reads first about a person: what they can ask for, what they were given in advance and their policy. */
export default function RecordSummary({
    available,
    advanced,
    policy,
}: {
    available: number;
    advanced: number;
    policy: string;
}) {
    const cards = [
        {
            icon: CalendarClock,
            label: 'Disponibles',
            value: `${available}`,
            hint: 'Días que puede pedir',
        },
        {
            icon: Hourglass,
            label: 'Adelantados',
            value: `${advanced}`,
            hint:
                advanced === 0
                    ? 'Sin días adelantados'
                    : `${countOf(advanced, 'día', 'días')} por descontar`,
        },
        {
            icon: ScrollText,
            label: 'Política',
            value: policy,
            hint: 'Reglas que sigue su periodo',
        },
    ];

    return (
        <ul className="grid gap-4 sm:grid-cols-3">
            {cards.map(({ icon: Icon, label, value, hint }) => (
                <li key={label} className="flex flex-col gap-3 card p-5">
                    <p className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <Icon aria-hidden className="size-4" />
                        {label}
                    </p>
                    <p className="text-3xl font-semibold tabular-nums">
                        {value}
                    </p>
                    <p className="text-meta text-muted-foreground">{hint}</p>
                </li>
            ))}
        </ul>
    );
}
