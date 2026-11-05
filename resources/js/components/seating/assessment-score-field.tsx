import InputError from '@/components/humetria/input-error';
import { conditionSource } from '@/lib/seating/assessment';
import { cn } from '@/lib/utils';
import type { AssessmentCondition } from '@/types';

const OPTION =
    'flex min-h-[88px] cursor-pointer flex-col justify-between gap-3 rounded-lg border border-input bg-card px-3 py-2.5 text-sm transition-colors duration-100 ease-out hover:bg-muted has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[11px] has-checked:py-[9px] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring has-disabled:cursor-default';

/**
 * One condition of Quinta D (board LS-Evaluacion): its letter, words and where it comes from, and the DOF's three options as
 * cards with their points; read-only once signed, with the chosen one marked. Discomfort without an answer is flagged.
 */
export default function AssessmentScoreField({
    condition,
    points,
    personAnswered,
    disabled,
    error,
    onChoose,
}: {
    condition: AssessmentCondition;
    points: number | undefined;
    personAnswered: boolean;
    disabled: boolean;
    error: string | undefined;
    onChoose: (points: number) => void;
}) {
    const source = conditionSource(condition.key, personAnswered);

    return (
        <fieldset
            disabled={disabled}
            className={cn(
                'grid gap-3 border-t px-5 py-4 first:border-t-0 @[720px]:grid-cols-[220px_minmax(0,1fr)] @[720px]:gap-5',
                source.unanswered && 'bg-warning',
            )}
        >
            <legend className="sr-only">
                {condition.letter}) {condition.title}
            </legend>
            <div className="flex flex-col items-start gap-1.5">
                <p className="flex items-start gap-2 font-medium">
                    <span
                        aria-hidden
                        className="inline-flex size-6 shrink-0 items-center justify-center rounded-md border bg-muted text-sm"
                    >
                        {condition.letter}
                    </span>
                    {condition.title}
                </p>
                <span className="rounded-md border bg-muted px-2 text-sm text-muted-foreground">
                    {source.label}
                </span>
                {source.unanswered && (
                    <p className="text-sm text-warning-foreground">
                        La persona no contestó. Vale 1 hasta que la Comisión la
                        corrija.
                    </p>
                )}
            </div>
            <div className="grid gap-2 @[520px]:grid-cols-3">
                {condition.options.map((option) => (
                    <label key={option.points} className={OPTION}>
                        <input
                            type="radio"
                            name={`scores[${condition.key}]`}
                            value={option.points}
                            defaultChecked={points === option.points}
                            onChange={() => onChoose(option.points)}
                            className="sr-only"
                        />
                        <span className="font-medium">{option.text}</span>
                        <span className="text-muted-foreground">
                            {option.points}{' '}
                            {option.points === 1 ? 'punto' : 'puntos'}
                        </span>
                    </label>
                ))}
            </div>
            <InputError message={error} />
        </fieldset>
    );
}
