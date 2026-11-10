import { CalendarClock, CalendarOff, Hourglass, Inbox } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { countOf } from '@/lib/plural';
import type { LeaveSummary } from '@/types';

type Card = {
    icon: LucideIcon;
    label: string;
    value: number;
    hint: string;
};

function cards(summary: LeaveSummary): Card[] {
    return [
        {
            icon: CalendarOff,
            label: 'Fuera hoy',
            value: summary.outToday,
            hint: `${summary.outThisWeek} esta semana`,
        },
        {
            icon: Inbox,
            label: 'Por responder',
            value: summary.pending,
            hint: `${countOf(summary.escalated, 'escalada', 'escaladas')} a RH`,
        },
        {
            icon: CalendarClock,
            label: 'Ventanas por cerrar',
            value: summary.windowsClosing,
            hint: 'Periodos cuyo plazo de disfrute está por vencer o venció',
        },
        {
            icon: Hourglass,
            label: 'Días por disfrutar',
            value: summary.liabilityDays,
            hint: `${countOf(summary.prescribableDays, 'día podría', 'días podrían')} prescribir`,
        },
    ];
}

/** The numbers RH reads first: who is out, what waits, which windows close and the days owed. Days and people, no amounts. */
export default function SummaryCards({ summary }: { summary: LeaveSummary }) {
    return (
        <section aria-labelledby="leave-summary">
            <h2 id="leave-summary" className="sr-only">
                Resumen de vacaciones
            </h2>
            <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {cards(summary).map(({ icon: Icon, label, value, hint }) => (
                    <li key={label} className="flex flex-col gap-3 card p-5">
                        <p className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <Icon aria-hidden className="size-4" />
                            {label}
                        </p>
                        <p className="text-3xl font-semibold tabular-nums">
                            {value}
                        </p>
                        <p className="text-meta text-muted-foreground">
                            {hint}
                        </p>
                    </li>
                ))}
            </ul>
            {summary.prescribableDays > 0 && (
                <p className="mt-3 text-meta text-muted-foreground">
                    Siguen en el saldo: oponer la prescripción es decisión de la
                    empresa.
                </p>
            )}
        </section>
    );
}
