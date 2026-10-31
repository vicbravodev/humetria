import { CircleCheck, Circle } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Label } from '@/components/ui/label';
import { filledSegments, passwordChecks } from '@/lib/password-checks';
import { cn } from '@/lib/utils';

/**
 * A new password with what the server asks of it checked while typing (pantalla: Restablecer): a four-part meter and
 * one line per rule. The rules come from the server's `passwordrules`, so they are the ones it will apply.
 */
export default function NewPasswordField({
    label,
    passwordRules,
    error,
}: {
    label: string;
    passwordRules: string;
    error: string | undefined;
}) {
    const [password, setPassword] = useState('');
    const checks = passwordChecks(passwordRules, password);
    const filled = filledSegments(checks);

    return (
        <div className="grid gap-1.5">
            <Label htmlFor="password">{label}</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autoFocus
                autoComplete="new-password"
                passwordrules={passwordRules}
                aria-describedby="password-checks"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
            />
            <div aria-hidden className="mt-1 grid grid-cols-4 gap-1">
                {[0, 1, 2, 3].map((segment) => (
                    <span
                        key={segment}
                        className={cn(
                            'h-1 rounded-full transition-colors duration-150',
                            segment < filled ? 'bg-primary' : 'bg-track',
                        )}
                    />
                ))}
            </div>
            <ul id="password-checks" className="mt-1 flex flex-col gap-1">
                {checks.map((check) => (
                    <li
                        key={check.label}
                        className="flex items-center gap-1.5 text-[13px] leading-[18px]"
                    >
                        {check.met ? (
                            <CircleCheck
                                aria-hidden
                                className="size-3.5 text-primary"
                            />
                        ) : (
                            <Circle
                                aria-hidden
                                className="size-3.5 text-muted-foreground"
                            />
                        )}
                        <span>
                            {check.label}
                            <span className="sr-only">
                                {check.met ? ': cumplido' : ': pendiente'}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
            <InputError message={error} />
        </div>
    );
}
