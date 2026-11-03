import { Alert, AlertDescription } from '@/components/ui/alert';
import type { Draft } from '@/lib/complaints/form';
import type { ComplaintKindOption, ComplaintTypeOption } from '@/types';

/** Step 4: what will be sent, to read once more. Sending is the person's decision, here and nowhere else. */
export default function ReviewStep({
    draft,
    types,
    kinds,
    errors,
}: {
    draft: Draft;
    types: ComplaintTypeOption[];
    kinds: ComplaintKindOption[];
    errors: Record<string, string | undefined>;
}) {
    const messages = Object.values(errors).filter(
        (message): message is string => message !== undefined,
    );
    const rows = [
        ['Qué informa', types.find((type) => type.value === draft.type)?.label],
        ['Lo que pasó', draft.description.trim()],
        ['Centro de trabajo', draft.workCenter?.label ?? 'Sin indicar'],
        [
            'De quién se trata',
            kinds.find((kind) => kind.value === draft.respondentKind)?.label,
        ],
        ['Cómo va', 'Anónima: sin nombre ni contacto'],
    ];

    return (
        <div className="flex flex-col gap-5">
            <h2 className="text-xl font-semibold">Revise antes de enviar</h2>
            <dl className="divide-y card">
                {rows.map(([label, value]) => (
                    <div
                        key={label}
                        className="grid gap-1 px-5 py-3 sm:grid-cols-3"
                    >
                        <dt className="text-base text-muted-foreground">
                            {label}
                        </dt>
                        <dd className="whitespace-pre-line sm:col-span-2">
                            {value}
                        </dd>
                    </div>
                ))}
            </dl>
            {messages.length > 0 && (
                <Alert variant="destructive" role="alert">
                    <AlertDescription>{messages.join(' ')}</AlertDescription>
                </Alert>
            )}
        </div>
    );
}
