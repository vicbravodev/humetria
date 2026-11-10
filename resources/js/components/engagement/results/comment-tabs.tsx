import CountTabs from '@/components/humetria/count-tabs';
import { comments } from '@/routes/tenant/climate';

const QUESTIONS: { value: string; label: string }[] = [
    { value: 'open.best', label: 'Lo mejor de trabajar aquí' },
    { value: 'open.improve', label: 'Lo que cambiarían' },
];

/** The two open questions about the company, one at a time. */
export default function CommentTabs({
    tenantSlug,
    surveyId,
    selected,
}: {
    tenantSlug: string;
    surveyId: string;
    selected: string;
}) {
    return (
        <CountTabs
            label="Pregunta abierta"
            tabs={QUESTIONS.map((question) => ({
                label: question.label,
                href: comments.url([tenantSlug, surveyId], {
                    query: { item: question.value },
                }),
                active: selected === question.value,
            }))}
        />
    );
}
