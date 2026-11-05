import InputError from '@/components/humetria/input-error';
import { ITEMS_57 } from '@/lib/disclosure';

/** Which of the six things of 5.7 a means covered, the policy marked at first (criterio C-52). */
export default function CoveredItemsField({
    error,
}: {
    error: string | undefined;
}) {
    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1 text-sm font-medium">
                Qué cubrió (numeral 5.7)
            </legend>
            {ITEMS_57.map(({ value, label }) => (
                <label
                    key={value}
                    className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border border-input bg-card px-4 py-2.5 hover:bg-muted has-checked:border-primary has-checked:bg-accent has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring"
                >
                    <input
                        type="checkbox"
                        name="covered_items[]"
                        value={value}
                        defaultChecked={value === 'a'}
                        className="size-5 shrink-0 accent-primary"
                    />
                    <span>
                        <span className="mr-1 font-medium">{value})</span>
                        {label}
                    </span>
                </label>
            ))}
            <InputError message={error} />
        </fieldset>
    );
}
