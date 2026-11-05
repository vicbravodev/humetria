import { Check } from 'lucide-react';
import FlowProgress from '@/components/humetria/flow-progress';
import WritePublish from '@/components/telework/write-publish';
import WriteMissing from '@/components/write-missing';
import { Button } from '@/components/ui/button';
import type { FlowQuestion } from '@/lib/question-flow';
import type { WritingPublication } from '@/types';

/**
 * The end of the questions (board V2-Politica): what is left to answer, each with its way back, or the document ready to
 * read on the right and to publish.
 */
export default function WriteReview({
    policy,
    missing,
    questions,
    publication,
    publishUrl,
    onGo,
}: {
    /** The Política de Teletrabajo, which names its 12 topics. */
    policy: boolean;
    missing: FlowQuestion[];
    questions: FlowQuestion[];
    publication: WritingPublication;
    publishUrl: string;
    onGo: (step: number) => void;
}) {
    if (missing.length > 0) {
        return (
            <WriteMissing missing={missing} questions={questions} onGo={onGo} />
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
            <WritePublish
                policy={policy}
                publication={publication}
                url={publishUrl}
            />
            <div>
                <Button variant="ghost" onClick={() => onGo(0)}>
                    Revisar las respuestas
                </Button>
            </div>
        </div>
    );
}
