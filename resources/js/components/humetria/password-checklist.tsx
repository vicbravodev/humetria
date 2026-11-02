import { CircleCheck, CircleDashed } from 'lucide-react';
import { filledSegments, passwordChecks } from '@/lib/password-rules';
import { cn } from '@/lib/utils';

/**
 * What a new password still needs while it is typed (pantallas: Restablecer, Ajustes): a four-part meter and one line
 * per rule, read from the server's `passwordrules` so they are the ones it will apply. `id` is what the field's
 * `aria-describedby` points to.
 */
export default function PasswordChecklist({
    id,
    rules,
    value,
}: {
    id: string;
    rules: string;
    value: string;
}) {
    const checks = passwordChecks(rules, value);
    const filled = filledSegments(checks);

    if (checks.length === 0) {
        return null;
    }

    return (
        <div id={id} className="mt-1 flex flex-col gap-1">
            <div aria-hidden className="grid grid-cols-4 gap-1">
                {[0, 1, 2, 3].map((segment) => (
                    <span
                        key={segment}
                        className={cn(
                            'h-1 rounded-full transition-colors duration-150 ease-out',
                            segment < filled ? 'bg-primary' : 'bg-track',
                        )}
                    />
                ))}
            </div>
            <ul className="mt-1 flex flex-col gap-1">
                {checks.map((check) => (
                    <li
                        key={check.label}
                        className={cn(
                            'flex items-center gap-1.5 text-caption',
                            !check.met && 'text-muted-foreground',
                        )}
                    >
                        {check.met ? (
                            <CircleCheck
                                aria-hidden
                                className="size-3.5 text-primary"
                            />
                        ) : (
                            <CircleDashed aria-hidden className="size-3.5" />
                        )}
                        {check.label}
                        <span className="sr-only">
                            {check.met ? ': cumplido' : ': pendiente'}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
