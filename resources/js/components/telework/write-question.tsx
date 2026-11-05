import FlowNav from '@/components/humetria/flow-nav';
import FlowProgress from '@/components/humetria/flow-progress';
import OptionRows from '@/components/humetria/option-rows';
import QuestionHeading from '@/components/humetria/question-heading';
import { minutesLeft } from '@/lib/question-flow';
import type { FlowAnswers, FlowQuestion } from '@/lib/question-flow';

/** One question of a NOM-037 document at a time (boards V2-Politica and V3-Doc-Riesgos), with where it goes. */
export default function WriteQuestion({
    questions,
    index,
    answers,
    minutes,
    onAnswer,
    onBack,
    onNext,
}: {
    questions: FlowQuestion[];
    index: number;
    answers: FlowAnswers;
    minutes: number;
    onAnswer: (field: string, value: string | string[]) => void;
    onBack: () => void;
    onNext: () => void;
}) {
    const question = questions[index];

    if (question === undefined) {
        return null;
    }

    return (
        <div className="flex flex-col gap-6">
            <FlowProgress
                label={`Pregunta ${index + 1} de ${questions.length}`}
                left={`Faltan unos ${minutesLeft(minutes, questions.length, index)} min`}
                value={index}
                max={questions.length}
            />
            <div key={question.key} className="flex flex-col gap-[22px]">
                <QuestionHeading
                    topic={question.topic}
                    title={question.title}
                    hint={question.hint}
                />
                {question.fields.map((field) => (
                    <OptionRows
                        key={field.key}
                        name={field.key}
                        legend={field.legend}
                        shown={field.shown}
                        options={field.options}
                        multiple={field.multiple}
                        columns={field.columns}
                        value={answers[field.key] ?? null}
                        onChange={(value) => onAnswer(field.key, value)}
                    />
                ))}
            </div>
            <FlowNav first={index === 0} onBack={onBack} onNext={onNext} />
        </div>
    );
}
