import GateStep from '@/components/campaigns/survey/gate-step';
import SurveyQuestion from '@/components/campaigns/survey/survey-question';
import type { Answers, Step } from '@/lib/survey';
import type { SurveyInstrument } from '@/types';

/** One screen: the section's instruction as the norm words it, then its questions; or a yes/no gate alone. */
export default function SurveyStep({
    instrument,
    step,
    answers,
    onAnswer,
}: {
    instrument: SurveyInstrument;
    step: Step;
    answers: Answers;
    onAnswer: (question: string, value: string) => void;
}) {
    const section = instrument.sections[step.section];

    if (step.gate !== null) {
        return (
            <GateStep
                gate={step.gate}
                value={answers[step.gate.key]}
                onAnswer={onAnswer}
            />
        );
    }

    if (section === undefined) {
        return null;
    }

    return (
        <div className="flex flex-col gap-8">
            <div className="flex flex-col gap-2 rounded-xl bg-accent p-5 text-accent-foreground">
                {section.title !== null && (
                    <p className="font-semibold">{section.title}</p>
                )}
                {section.instruction !== '' && (
                    <p className="text-lg leading-7">{section.instruction}</p>
                )}
            </div>
            {step.items.map((item) => (
                <SurveyQuestion
                    key={item.number}
                    name={`item-${item.number}`}
                    caption={`Pregunta ${section.items.indexOf(item) + 1} de ${section.items.length}`}
                    text={item.text}
                    options={instrument.options}
                    value={answers[String(item.number)]}
                    onChange={(value) => onAnswer(String(item.number), value)}
                />
            ))}
        </div>
    );
}
