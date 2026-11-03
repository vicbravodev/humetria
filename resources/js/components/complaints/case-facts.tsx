import {
    CalendarClock,
    CalendarDays,
    Factory,
    Fingerprint,
    Tag,
    UserRound,
} from 'lucide-react';
import { describeDate } from '@/lib/calendar-date';
import type { CaseDetail } from '@/types';

/** The card of a case (pantallas: Eval-Activa's ficha): what it is, where, against whom, and the days. */
export default function CaseFacts({ detail }: { detail: CaseDetail }) {
    const day = (value: string | null) =>
        value === null ? null : (describeDate(value) ?? value);
    const facts = [
        {
            icon: Tag,
            label: 'Tipo',
            value:
                detail.categoryLabel === null
                    ? detail.typeLabel
                    : `${detail.typeLabel} · ${detail.categoryLabel}`,
        },
        {
            icon: Factory,
            label: 'Centro de trabajo',
            value: detail.center ?? 'Sin indicar',
        },
        { icon: UserRound, label: 'Contra', value: detail.kindLabel },
        {
            icon: Fingerprint,
            label: 'Cómo fue presentada',
            value:
                detail.identity === 'anonymous'
                    ? 'Anónima'
                    : 'Confidencial, con nombre',
        },
        {
            icon: CalendarDays,
            label: 'Recibida',
            value: day(detail.receivedOn),
        },
        {
            icon: CalendarClock,
            label: 'Ocurrió',
            value: day(detail.occurredOn) ?? 'Sin indicar',
        },
    ];

    return (
        <dl className="grid gap-x-6 gap-y-4 card p-5 sm:grid-cols-2">
            {facts.map(({ icon: Icon, label, value }) => (
                <div key={label} className="flex flex-col gap-1">
                    <dt className="flex items-center gap-2.5 text-caption text-muted-foreground">
                        <span className="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                            <Icon aria-hidden className="size-[18px]" />
                        </span>
                        {label}
                    </dt>
                    <dd className="pl-[46px] font-medium">{value}</dd>
                </div>
            ))}
        </dl>
    );
}
