import { countOf } from '@/lib/plural';

/** The deadlines the company set for the channel — acknowledgement, answer and how long it is kept — as three facts. */
export default function ProcedureFacts({
    acknowledgeWithinDays,
    resolveWithinDays,
    retentionMonths,
}: {
    acknowledgeWithinDays: number;
    resolveWithinDays: number;
    retentionMonths: number;
}) {
    const facts = [
        ['Acuse de recibo', countOf(acknowledgeWithinDays, 'día', 'días')],
        ['Respuesta', countOf(resolveWithinDays, 'día', 'días')],
        [
            'Se conserva',
            `${countOf(retentionMonths, 'mes', 'meses')} tras cerrar`,
        ],
    ];

    return (
        <dl className="grid gap-4 border-t pt-5 sm:grid-cols-3">
            {facts.map(([label, value]) => (
                <div key={label} className="flex flex-col gap-0.5">
                    <dt className="text-sm text-muted-foreground">{label}</dt>
                    <dd className="text-lg font-medium">{value}</dd>
                </div>
            ))}
        </dl>
    );
}
