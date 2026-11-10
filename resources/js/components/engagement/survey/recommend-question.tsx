import PillChoice from '@/components/humetria/pill-choice';
import QuestionHeading from '@/components/humetria/question-heading';
import { focusOnMount } from '@/lib/focus';

const SCALE = Array.from({ length: 11 }, (_, number) => ({
    value: String(number),
    label: String(number),
}));

/** The recommendation question: a number from 0 to 10, in pills of 44 px. Choosing one only marks it. */
export default function RecommendQuestion({
    name,
    topic,
    text,
    value,
    onChange,
}: {
    name: string;
    topic: string;
    text: string;
    value: number | undefined;
    onChange: (value: number) => void;
}) {
    return (
        <div className="flex flex-col gap-5">
            <div
                ref={focusOnMount}
                tabIndex={-1}
                className="focus:outline-none"
            >
                <QuestionHeading topic={topic} title={text} hint={null} />
            </div>
            <PillChoice
                name={name}
                legend="Elija un número del 0 al 10"
                options={SCALE}
                value={value === undefined ? null : String(value)}
                onChange={(chosen) => onChange(Number(chosen))}
            />
        </div>
    );
}
