import InputError from '@/components/humetria/input-error';
import { CHOICE_CLASS } from '@/lib/seating/choice-class';
import { cn } from '@/lib/utils';
import type { PortalCondition } from '@/types';

/**
 * One of the two questions (board LS-Portal): where the person is, the question in plain words and the DOF's three answers
 * as cards, nothing pre-chosen. It stays mounted while hidden, so the form sends both answers.
 */
export default function PortalQuestion({
    number,
    question,
    name,
    condition,
    hidden,
    error,
    onChoose,
}: {
    number: 1 | 2;
    question: string;
    name: string;
    condition: PortalCondition;
    hidden: boolean;
    error: string | undefined;
    onChoose: () => void;
}) {
    return (
        <fieldset hidden={hidden} className="flex flex-col gap-4">
            <p className="text-base text-muted-foreground">
                Pregunta {number} de 2
            </p>
            <div aria-hidden className="grid grid-cols-2 gap-1.5">
                {[1, 2].map((step) => (
                    <span
                        key={step}
                        className={cn(
                            'h-1.5 rounded-full',
                            step <= number ? 'bg-primary' : 'bg-muted',
                        )}
                    />
                ))}
            </div>
            <legend className="sr-only">{question}</legend>
            <h1 className="text-[22px] leading-[30px] font-semibold">
                {question}
            </h1>
            <div className="grid gap-2.5">
                {Object.entries(condition.options).map(([points, text]) => (
                    <label
                        key={points}
                        className={cn(CHOICE_CLASS, 'min-h-[60px]')}
                    >
                        <input
                            type="radio"
                            name={name}
                            value={points}
                            onChange={onChoose}
                            className="size-5 shrink-0 accent-primary"
                        />
                        {text}
                    </label>
                ))}
            </div>
            <InputError message={error} />
        </fieldset>
    );
}
