import SurveyQuestion from '@/components/campaigns/survey/survey-question';
import type { SurveyGate } from '@/types';

const YES_NO = [
    { value: 'yes', label: 'Sí' },
    { value: 'no', label: 'No' },
];

/** A yes/no question that decides whether a block is asked, with the norm's instruction under it. */
export default function GateStep({
    gate,
    value,
    onAnswer,
}: {
    gate: SurveyGate;
    value: string | undefined;
    onAnswer: (question: string, value: string) => void;
}) {
    return (
        <div className="flex flex-col gap-4">
            <SurveyQuestion
                name={gate.key}
                text={gate.question}
                options={YES_NO}
                value={value}
                onChange={(answer) => onAnswer(gate.key, answer)}
            />
            <p className="text-lg leading-7 text-muted-foreground">
                {gate.instruction}
            </p>
        </div>
    );
}
