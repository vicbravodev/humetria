import { CircleCheck, CircleDashed } from 'lucide-react';
import { passwordChecks } from '@/lib/password-rules';
import { cn } from '@/lib/utils';

/** What the new password still needs, as a bar of segments and a list that ticks off as it is typed. */
export default function PasswordChecklist({
    rules,
    value,
}: {
    rules: string;
    value: string;
}) {
    const checks = passwordChecks(rules, value);

    const met = checks.filter((check) => check.met).length;

    if (checks.length === 0) {
        return null;
    }

    return (
        <div id="password-checklist" className="mt-1 flex flex-col gap-1">
            <div aria-hidden className="flex gap-1">
                {checks.map((check, index) => (
                    <span
                        key={check.label}
                        className={cn(
                            'h-1 flex-1 rounded-full transition-colors duration-150 ease-out',
                            index < met ? 'bg-primary' : 'bg-track',
                        )}
                    />
                ))}
            </div>
            <ul className="mt-1 flex flex-col gap-1">
                {checks.map((check) => (
                    <li
                        key={check.label}
                        className={cn(
                            'flex items-center gap-1.5 text-[13px] leading-[18px]',
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
