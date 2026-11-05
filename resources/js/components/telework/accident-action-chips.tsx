import { cn } from '@/lib/utils';
import type { Choice } from '@/types';

/**
 * What was done about an accident, as checkbox chips in a row (board V3-Aviso): native checkboxes sent as `actions[]`, with
 * a 44 px touch target; the chosen ones take the accent.
 */
export default function AccidentActionChips({
    choices,
    chosen,
    onChange,
}: {
    choices: Choice[];
    chosen: string[];
    onChange: (chosen: string[]) => void;
}) {
    return (
        <fieldset className="flex flex-col gap-2">
            <legend className="sr-only">Qué se hizo</legend>
            <div className="flex flex-wrap gap-2">
                {choices.map((choice) => (
                    <label
                        key={choice.value}
                        className={cn(
                            'inline-flex min-h-11 cursor-pointer items-center gap-2.5 rounded-lg border border-input bg-card px-3.5 transition-colors duration-100 ease-out hover:bg-muted',
                            'has-checked:border-primary has-checked:bg-accent has-checked:font-medium',
                            'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                        )}
                    >
                        <input
                            type="checkbox"
                            name="actions[]"
                            value={choice.value}
                            checked={chosen.includes(choice.value)}
                            onChange={(event) =>
                                onChange(
                                    event.currentTarget.checked
                                        ? [...chosen, choice.value]
                                        : chosen.filter(
                                              (value) => value !== choice.value,
                                          ),
                                )
                            }
                            className="size-4 shrink-0 accent-primary"
                        />
                        {choice.label}
                    </label>
                ))}
            </div>
        </fieldset>
    );
}
