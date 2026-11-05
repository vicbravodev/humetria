import FlowProgress from '@/components/humetria/flow-progress';
import { Button } from '@/components/ui/button';
import type { FlowQuestion } from '@/lib/question-flow';

/** The end of the questions with some still open (board V2-Politica): what is left to answer, each with its way back. */
export default function WriteMissing({
    missing,
    questions,
    onGo,
}: {
    missing: FlowQuestion[];
    questions: FlowQuestion[];
    onGo: (step: number) => void;
}) {
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
                            onClick={() => onGo(questions.indexOf(question))}
                        >
                            {question.topic}
                        </Button>
                    </li>
                ))}
            </ul>
        </div>
    );
}
