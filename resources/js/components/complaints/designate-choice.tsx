/** One of the ways to designate (board V4-Quejas-Designar): a 52 px card with its name and what it means. */
export default function DesignateChoice({
    value,
    label,
    hint,
    checked,
    disabled,
    onChange,
}: {
    value: string;
    label: string;
    hint: string;
    checked: boolean;
    disabled: boolean;
    onChange: (value: string) => void;
}) {
    return (
        <label className="flex min-h-[52px] cursor-pointer items-start gap-3 rounded-lg border border-input bg-card px-4 py-3 transition-colors hover:bg-muted has-checked:border-primary has-checked:bg-accent has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring has-disabled:cursor-not-allowed has-disabled:opacity-60">
            <input
                type="radio"
                name="designate-from"
                value={value}
                checked={checked}
                disabled={disabled}
                onChange={() => onChange(value)}
                className="mt-0.5 size-5 shrink-0 accent-primary"
            />
            <span className="flex flex-col">
                <span className="font-medium">{label}</span>
                <span className="text-sm text-muted-foreground">{hint}</span>
            </span>
        </label>
    );
}
