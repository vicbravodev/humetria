import { describeDate } from '@/lib/calendar-date';
import type { CaseDetail } from '@/types';

/** How the person presented it: an anonymous case has nobody to call, a confidential one shows who, to the assigned only. */
function presented(detail: CaseDetail): string {
    if (detail.identity === 'anonymous') {
        return 'Anónima: no sabemos quién es. Solo puedes responder por aquí.';
    }

    const contact = detail.reporterContact ?? 'sin forma de contacto';

    return `Confidencial: ${detail.reporterName ?? 'con su nombre'}, ${contact}. Solo lo ven quienes tienen el caso.`;
}

/** «Lo que cuenta la persona» (board V4-Quejas-Caso): their words as they wrote them, and what frames them. */
export default function CaseStory({ detail }: { detail: CaseDetail }) {
    const facts: Array<[string, string | null]> = [
        ['Contra', detail.kindLabel],
        ['Sobre la persona señalada', detail.respondentText],
        ['Personas involucradas', detail.involvedText],
        ['Clasificación', detail.categoryLabel],
        [
            'Cuándo ocurrió',
            detail.occurredOn === null
                ? 'Sin indicar'
                : (describeDate(detail.occurredOn) ?? detail.occurredOn),
        ],
        ['Cómo la presentó', presented(detail)],
    ];

    return (
        <section
            aria-labelledby="case-story"
            className="flex flex-col gap-5 card p-6"
        >
            <h2 id="case-story" className="text-lg font-semibold">
                Lo que cuenta la persona
            </h2>
            <blockquote className="border-l-4 border-border pl-4 text-lg leading-7 whitespace-pre-line">
                {detail.description}
            </blockquote>
            <dl className="@container grid gap-x-6 gap-y-2 sm:grid-cols-[minmax(0,10rem)_1fr]">
                {facts
                    .filter(
                        (fact): fact is [string, string] => fact[1] !== null,
                    )
                    .map(([label, value]) => (
                        <div key={label} className="contents">
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd className="whitespace-pre-line max-sm:mb-2">
                                {value}
                            </dd>
                        </div>
                    ))}
            </dl>
        </section>
    );
}
