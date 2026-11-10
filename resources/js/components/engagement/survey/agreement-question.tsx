import OptionRows from '@/components/humetria/option-rows';
import QuestionHeading from '@/components/humetria/question-heading';
import { focusOnMount } from '@/lib/focus';

/** One statement answered on five points of agreement, as full-width rows. Choosing one only marks it. */
export default function AgreementQuestion({
    name,
    topic,
    text,
    labels,
    value,
    onChange,
}: {
    name: string;
    topic: string;
    text: string;
    labels: string[];
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
            <OptionRows
                name={name}
                legend={text}
                shown={false}
                multiple={false}
                columns={1}
                options={labels.map((label, index) => ({
                    value: String(index + 1),
                    label,
                }))}
                value={value === undefined ? null : String(value)}
                onChange={(chosen) => onChange(Number(chosen))}
            />
        </div>
    );
}
