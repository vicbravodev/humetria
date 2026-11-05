import { Check } from 'lucide-react';
import FlowProgress from '@/components/humetria/flow-progress';
import { Button } from '@/components/ui/button';
import type { FlowQuestion } from '@/lib/question-flow';

/**
 * The end of the questions (board V2-Politica): what is left to answer, each with its way back, or the document ready to
 * read on the right and to change.
 */
export default function WriteReview({
    policy,
    missing,
    questions,
    onGo,
}: {
    /** The Política de Teletrabajo, which names its 12 topics. */
    policy: boolean;
    missing: FlowQuestion[];
    questions: FlowQuestion[];
    onGo: (step: number) => void;
}) {
    if (missing.length > 0) {
        return (
            <div className="flex flex-col gap-6">
                <FlowProgress
                    label="Casi listo"
                    left={null}
                    value={questions.length - missing.length}
                    max={questions.length}
                />
                <h1 className="text-2xl leading-8 font-semibold tracking-[-0.01em] text-balance md:text-[26px] md:leading-[34px]">
                    {missing.length === 1
                        ? 'Te falta contestar una pregunta'
                        : `Te faltan ${missing.length} preguntas`}
                </h1>
                <ul className="flex flex-col gap-2.5">
                    {missing.map((question) => (
                        <li key={question.key}>
                            <Button
                                variant="outline"
                                size="lg"
                                onClick={() =>
                                    onGo(questions.indexOf(question))
                                }
                            >
                                {question.topic}
                            </Button>
                        </li>
                    ))}
                </ul>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-[18px]">
            <FlowProgress
                label="Lista para revisar"
                left={null}
                value={questions.length}
                max={questions.length}
            />
            <span
                aria-hidden
                className="inline-flex size-[52px] items-center justify-center rounded-full bg-primary text-primary-foreground"
            >
                <Check className="size-[26px]" strokeWidth={2.5} />
            </span>
            <h1 className="text-[28px] leading-9 font-semibold tracking-[-0.01em]">
                {policy
                    ? 'Tu política cubre los 12 temas'
                    : 'Tu documento está listo'}
            </h1>
            <p className="text-[17px] leading-[26px] text-muted-foreground">
                {policy ? 'Léela' : 'Léelo'} a la derecha y cambia lo que
                quieras.
            </p>
            <div>
                <Button variant="ghost" onClick={() => onGo(0)}>
                    Revisar las respuestas
                </Button>
            </div>
        </div>
    );
}
