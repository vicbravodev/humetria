import InputError from '@/components/input-error';
import type { ReplacementPool } from '@/types';

/** The gender of who will not answer, with how many people of it are left outside the sample. */
export default function ReplacementGenderField({
    pool,
    error,
}: {
    pool: ReplacementPool[];
    error: string | undefined;
}) {
    return (
        <fieldset
            className="grid gap-3"
            aria-describedby={
                error === undefined ? undefined : 'replacements-gender-error'
            }
        >
            <legend className="mb-1 text-sm leading-none font-medium">
                ¿De qué género son quienes no contestarán?
            </legend>
            {pool.map((option, index) => (
                <label
                    key={option.gender}
                    className="flex cursor-pointer items-center justify-between gap-3 rounded-lg border p-3 has-checked:border-primary has-checked:bg-accent"
                >
                    <span className="flex items-center gap-3">
                        <input
                            type="radio"
                            name="gender"
                            value={option.gender}
                            defaultChecked={index === 0}
                            disabled={option.available === 0}
                            className="size-5 shrink-0 accent-primary"
                        />
                        <span className="text-sm font-medium">
                            {option.label}
                        </span>
                    </span>
                    <span className="text-sm text-muted-foreground">
                        {option.available === 1
                            ? 'queda 1'
                            : `quedan ${option.available}`}
                    </span>
                </label>
            ))}
            <InputError
                id="replacements-gender-error"
                role="alert"
                message={error}
            />
        </fieldset>
    );
}
