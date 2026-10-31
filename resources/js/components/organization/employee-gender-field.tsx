import InputError from '@/components/input-error';
import { cn } from '@/lib/utils';
import type { ChoiceOption } from '@/types';

/** The gender as three side-by-side choices (native radios): the norm uses it to draw the sample. */
export default function EmployeeGenderField({
    genders,
    defaultValue,
    error,
}: {
    genders: ChoiceOption[];
    defaultValue: string | undefined;
    error: string | undefined;
}) {
    return (
        <fieldset
            className="grid gap-2"
            aria-invalid={error === undefined ? undefined : true}
            aria-describedby={
                error === undefined
                    ? 'employee-gender-help'
                    : 'employee-gender-help employee-gender-error'
            }
        >
            <legend className="mb-2 text-sm leading-5 font-medium">
                Género
            </legend>
            <div
                className={cn(
                    'grid grid-cols-3 gap-0.5 rounded-[10px] border bg-muted p-[3px]',
                    error !== undefined && 'border-destructive',
                )}
            >
                {genders.map((gender) => (
                    <label
                        key={gender.value}
                        className="flex min-h-[38px] cursor-pointer items-center justify-center rounded-lg px-2 text-center text-sm leading-[18px] font-medium text-muted-foreground transition-colors duration-150 ease-out hover:text-foreground has-checked:bg-card has-checked:text-foreground has-checked:shadow-raised has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring"
                    >
                        <input
                            type="radio"
                            name="gender"
                            value={gender.value}
                            defaultChecked={defaultValue === gender.value}
                            className="sr-only"
                        />
                        {gender.label}
                    </label>
                ))}
            </div>
            <p
                id="employee-gender-help"
                className="text-[13px] leading-[18px] text-muted-foreground"
            >
                La norma lo usa para sortear la muestra.
            </p>
            <InputError
                id="employee-gender-error"
                role="alert"
                message={error}
            />
        </fieldset>
    );
}
