import InputError from '@/components/humetria/input-error';
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
            {/* The chosen one speaks PillChoice's language (accent, primary border, medium weight), in three equal columns. */}
            <div className="grid grid-cols-3 gap-2">
                {genders.map((gender) => (
                    <label
                        key={gender.value}
                        className={cn(
                            'flex min-h-11 min-w-0 cursor-pointer items-center justify-center rounded-lg border border-input bg-card px-2 text-center transition-colors duration-100 ease-out hover:bg-muted',
                            'has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[7px] has-checked:font-medium',
                            'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                            error !== undefined && 'border-destructive',
                        )}
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
                className="text-caption text-muted-foreground"
            >
                Elige «Otro» si prefieres no decirlo. La norma lo usa para
                sortear la muestra.
            </p>
            <InputError id="employee-gender-error" message={error} />
        </fieldset>
    );
}
