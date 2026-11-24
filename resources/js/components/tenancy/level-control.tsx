import { cn } from '@/lib/utils';

/**
 * The access level of one area as a segmented control (board Roles 7c: «Sin acceso · Ver · Administrar»): native radios, so
 * the arrows move between them and the focus shows, equal columns, 44 px tall on a phone. With a person's role as `floor`,
 * the levels under it are still and the role's own says «su rol». No level checked means a set saved before levels; without
 * `onChange` it is only read.
 */
export default function LevelControl({
    name,
    label,
    levels,
    value,
    floor,
    onChange,
}: {
    name: string;
    /** The area, for screen readers. */
    label: string;
    levels: { key: string; label: string }[];
    value: string | null;
    /** A person's role's level, by position; undefined for a role itself. */
    floor?: number | undefined;
    onChange: ((level: string) => void) | undefined;
}) {
    return (
        <fieldset className="w-full shrink-0 sm:w-auto">
            <legend className="sr-only">Nivel de acceso a {label}</legend>
            <div className="grid w-full auto-cols-fr grid-flow-col overflow-hidden rounded-lg border border-input bg-card sm:inline-grid sm:w-auto">
                {levels.map((level, index) => {
                    const below = index < (floor ?? 0);
                    const own = index === floor;

                    return (
                        <label
                            key={level.key}
                            data-test={`level-${name}-${level.key}`}
                            className={cn(
                                'relative flex min-h-11 cursor-pointer items-center justify-center px-3.5 text-center text-sm font-medium whitespace-nowrap transition-colors duration-100 ease-out hover:bg-muted sm:min-h-9',
                                index > 0 && 'border-l border-input',
                                'has-focus-visible:z-10 has-focus-visible:outline-2 has-focus-visible:-outline-offset-2 has-focus-visible:outline-ring',
                                'has-checked:bg-primary has-checked:font-semibold has-checked:text-primary-foreground',
                                own &&
                                    'has-checked:bg-accent has-checked:text-foreground',
                                'has-disabled:cursor-not-allowed has-disabled:hover:bg-transparent',
                                below &&
                                    'bg-muted font-normal text-muted-foreground',
                            )}
                        >
                            <input
                                type="radio"
                                name={`level-${name}`}
                                value={level.key}
                                checked={value === level.key}
                                disabled={onChange === undefined || below}
                                onChange={() => onChange?.(level.key)}
                                className="sr-only"
                            />
                            {level.label}
                            {own && index > 0 && (
                                <span className="ml-1 font-normal">
                                    · su rol
                                </span>
                            )}
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}
