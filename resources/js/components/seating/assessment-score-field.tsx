import InputError from '@/components/humetria/input-error';
import { CHOICE_CLASS } from '@/lib/seating/choice-class';
import type { AssessmentCondition } from '@/types';

/** One condition of Quinta D with the DOF's three options; read-only once signed, with the chosen one marked. */
export default function AssessmentScoreField({
    condition,
    points,
    disabled,
    error,
}: {
    condition: AssessmentCondition;
    points: number | undefined;
    disabled: boolean;
    error: string | undefined;
}) {
    return (
        <fieldset className="grid gap-2" disabled={disabled}>
            <legend className="mb-1 font-medium">
                {condition.letter}) {condition.title}
            </legend>
            {condition.options.map((option) => (
                <label key={option.points} className={CHOICE_CLASS}>
                    <input
                        type="radio"
                        name={`scores[${condition.key}]`}
                        value={option.points}
                        defaultChecked={points === option.points}
                        className="size-5 shrink-0 accent-primary"
                    />
                    <span>
                        {option.text}
                        <span className="ml-2 text-caption text-muted-foreground">
                            {option.points}{' '}
                            {option.points === 1 ? 'punto' : 'puntos'}
                        </span>
                    </span>
                </label>
            ))}
            <InputError message={error} />
        </fieldset>
    );
}
