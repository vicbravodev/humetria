import GateStep from '@/components/campaigns/survey/gate-step';
import SurveyQuestion from '@/components/campaigns/survey/survey-question';
import { focusOnMount } from '@/lib/focus';
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
        <div className="flex flex-col gap-5 md:gap-0">
            <div
                ref={focusOnMount}
                tabIndex={-1}
                className="flex flex-col gap-1 rounded-xl bg-accent px-4 py-3.5 text-[17px] leading-[26px] text-accent-foreground focus:outline-none md:mb-2 md:px-[18px] md:py-4 md:text-lg md:leading-7"
            >
                {section.title !== null && (
                    <p className="font-semibold">{section.title}</p>
                )}
                {section.instruction !== '' && <p>{section.instruction}</p>}
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
