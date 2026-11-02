import { useState } from 'react';
import PasswordChecklist from '@/components/humetria/password-checklist';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Label } from '@/components/ui/label';

/**
 * A new password with what the server asks of it checked while typing (pantalla: Restablecer).
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
            <PasswordChecklist
                id="password-checks"
                rules={passwordRules}
                value={password}
            />
            <InputError message={error} />
        </div>
    );
}
