import type { SurveyQuestion } from '@/types';

const SEGMENTS = [
    { key: 'yes', label: 'Sí', tone: 'bg-primary' },
    { key: 'no', label: 'No', tone: 'bg-primary/40' },
    { key: 'notApplicable', label: 'No aplica', tone: 'bg-muted' },
] as const;

/** One question counted: its code and words, a bar of the three answers and the written count behind it. */
export default function SurveyQuestionRow({
    question,
}: {
    question: SurveyQuestion;
}) {
    return (
        <li className="grid gap-2 py-3 first:pt-0 last:pb-0 @2xl:grid-cols-[minmax(0,1fr)_16rem] @2xl:items-center @2xl:gap-5">
            <p className="leading-5">
                <span className="text-muted-foreground tabular-nums">
                    {question.code}
                </span>{' '}
                {question.text}
            </p>
            {question.visible ? (
                <div className="grid gap-1.5">
                    <div
                        role="img"
                        aria-label={SEGMENTS.map(
                            ({ key, label }) => `${label}: ${question[key]}`,
                        ).join(', ')}
                        className="flex h-2 w-full overflow-hidden rounded-full bg-muted"
                    >
                        {SEGMENTS.map(({ key, tone }) => (
                            <div
                                key={key}
                                className={tone}
                                style={{ flexGrow: question[key] }}
                            />
                        ))}
                    </div>
                    <p className="text-sm text-muted-foreground tabular-nums">
                        {SEGMENTS.map(
                            ({ key, label }) => `${label} ${question[key]}`,
                        ).join(' · ')}
                    </p>
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Respondieron pocas personas: no se muestra
                </p>
            )}
        </li>
    );
}
