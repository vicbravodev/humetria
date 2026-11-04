import { describeDate } from '@/lib/calendar-date';
import { countOf } from '@/lib/plural';
import type { EvidenceChannel } from '@/types';

/** How the channel is set up: where it is, since when, what it accepts, how soon it answers and how long it keeps a case. */
export default function EvidenceMechanism({
    channel,
    types,
}: {
    channel: EvidenceChannel;
    types: { value: string; label: string }[];
}) {
    const published =
        channel.publishedOn === null
            ? undefined
            : describeDate(channel.publishedOn);
    const rows = [
        ['Dirección del canal', channel.url ?? 'Todavía no está activo'],
        ['Disponible desde', published ?? '—'],
        ['Qué recibe', types.map((type) => type.label).join('; ')],
        [
            'Cómo puede presentarse',
            'Anónima o confidencial, con referencia y clave para dar seguimiento',
        ],
        [
            'Acuse de recibo',
            `Dentro de ${countOf(channel.acknowledgeWithinDays, 'día', 'días')} (plazo que se fija la empresa)`,
        ],
        [
            'Respuesta',
            `Dentro de ${countOf(channel.resolveWithinDays, 'día', 'días')} (plazo que se fija la empresa)`,
        ],
        [
            'Conservación',
            `${countOf(channel.retentionMonths, 'mes', 'meses')} después del cierre; después se borra el contenido y queda un esqueleto sin datos personales`,
        ],
    ];

    return (
        <dl className="divide-y card">
            {rows.map(([label, value]) => (
                <div
                    key={label}
                    className="grid gap-1 px-5 py-3 sm:grid-cols-3"
                >
                    <dt className="text-base text-muted-foreground">{label}</dt>
                    <dd className="break-words sm:col-span-2">{value}</dd>
                </div>
            ))}
        </dl>
    );
}
