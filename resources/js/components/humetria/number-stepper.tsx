import { Minus, Plus } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * A number moved with − and + or typed (board V4-Comp-Escenario: the raise, the months of retroactivity). The value goes
 * in a named input, so a form sends it as it is; the buttons are 44 px and say what they do.
 */
export default function NumberStepper({
    id,
    name,
    label,
    value,
    suffix,
    hint,
    error,
    placeholder,
    onChange,
    onStep,
}: {
    id: string;
    name: string;
    label: string;
    value: string;
    /** What the number counts, after it: "%", "al mes", "meses". */
    suffix: string;
    hint?: string | undefined;
    /** Shown while it is empty: what an empty value means. */
    placeholder?: string | undefined;
    error?: string | undefined;
    onChange: (value: string) => void;
    onStep: (direction: 1 | -1) => void;
}) {
    const described = [
        hint === undefined ? null : `${id}-hint`,
        error === undefined ? null : `${id}-error`,
    ]
        .filter((part) => part !== null)
        .join(' ');

    return (
        <div className="grid content-start gap-2">
            <Label htmlFor={id}>{label}</Label>
            <div
                className={cn(
                    'flex min-h-12 items-center rounded-lg border border-input bg-card has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                    error !== undefined && 'border-destructive',
                )}
            >
                <button
                    type="button"
                    aria-label={`Bajar ${label.toLowerCase()}`}
                    onClick={() => onStep(-1)}
                    className="grid size-11 shrink-0 place-items-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    <Minus aria-hidden className="size-4" />
                </button>
                <span className="flex min-w-0 flex-1 items-center justify-center gap-1.5 text-lg font-medium tabular-nums">
                    <input
                        id={id}
                        name={name}
                        value={value}
                        placeholder={placeholder}

                        inputMode="decimal"
                        autoComplete="off"
                        aria-invalid={error === undefined ? undefined : true}
                        aria-describedby={
                            described === '' ? undefined : described
                        }
                        onChange={(event) => onChange(event.target.value)}
                        className="field-sizing-content h-11 max-w-[9ch] min-w-[1ch] bg-transparent text-right outline-none placeholder:text-muted-foreground"
                    />
                    <span className="shrink-0">{suffix}</span>
                </span>
                <button
                    type="button"
                    aria-label={`Subir ${label.toLowerCase()}`}
                    onClick={() => onStep(1)}
                    className="grid size-11 shrink-0 place-items-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    <Plus aria-hidden className="size-4" />
                </button>
            </div>
            {hint !== undefined && (
                <p
                    id={`${id}-hint`}
                    className="text-caption text-muted-foreground"
                >
                    {hint}
                </p>
            )}
            {error !== undefined && (
                <p
                    id={`${id}-error`}
                    role="alert"
                    className="text-sm text-destructive"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
