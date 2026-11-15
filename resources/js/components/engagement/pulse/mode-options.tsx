import { modeHint } from '@/lib/engagement/pulse';
import type { PulseModeOption } from '@/types';

/** «¿Qué quieres mirar en este pulso?»: one radio per mode with what it looks at, the first chosen. */
export default function ModeOptions({
    modes,
    error,
}: {
    modes: PulseModeOption[];
    error: string | undefined;
}) {
    return (
        <fieldset className="flex flex-col gap-2">
            <legend className="mb-2 text-sm font-medium">
                ¿Qué quieres mirar en este pulso?
            </legend>
            {modes.map((mode, position) => (
                <label
                    key={mode.value}
                    className="flex min-h-[52px] cursor-pointer items-start gap-3 rounded-lg border border-input bg-card px-4 py-3 transition-colors hover:bg-muted has-checked:border-primary has-checked:bg-accent has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring"
                >
                    <input
                        type="radio"
                        name="mode"
                        value={mode.value}
                        defaultChecked={position === 0}
                        className="mt-0.5 size-5 shrink-0 accent-primary"
                    />
                    <span className="flex flex-col">
                        <span className="font-medium">{mode.label}</span>
                        <span className="text-sm text-muted-foreground">
                            {modeHint(mode.value)}
                        </span>
                    </span>
                </label>
            ))}
            {error !== undefined && (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            )}
        </fieldset>
    );
}
