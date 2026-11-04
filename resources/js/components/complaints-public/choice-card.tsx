import type { ReactNode } from 'react';

/** One option of a choice, as a card with a radio (pantallas: Usuarios-Invitar): its name, what it means, and why it is not offered if it is not. */
export default function ChoiceCard({
    name,
    value,
    checked,
    disabled = false,
    onSelect,
    children,
}: {
    name: string;
    value: string;
    checked: boolean;
    disabled?: boolean;
    onSelect: (value: string) => void;
    children: ReactNode;
}) {
    return (
        <label className="flex min-h-14 cursor-pointer items-start gap-3 rounded-lg border border-input bg-card px-4 py-3 transition-colors duration-100 ease-out hover:bg-muted has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[15px] has-checked:py-[11px] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring has-disabled:cursor-not-allowed has-disabled:opacity-60">
            <input
                type="radio"
                name={name}
                value={value}
                checked={checked}
                disabled={disabled}
                onChange={() => onSelect(value)}
                className="mt-0.5 size-5 shrink-0 accent-primary"
            />
            <span className="flex flex-col">{children}</span>
        </label>
    );
}
