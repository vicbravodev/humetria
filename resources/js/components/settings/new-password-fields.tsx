import type { RefObject } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import PasswordChecklist from '@/components/settings/password-checklist';
import { Label } from '@/components/ui/label';

/** The current password, the new one with what it still needs, and the new one again. */
export default function NewPasswordFields({
    errors,
    passwordRules,
    password,
    onPasswordChange,
    currentPasswordInput,
    passwordInput,
}: {
    errors: Partial<Record<string, string>>;
    passwordRules: string;
    password: string;
    onPasswordChange: (password: string) => void;
    currentPasswordInput: RefObject<HTMLInputElement | null>;
    passwordInput: RefObject<HTMLInputElement | null>;
}) {
    return (
        <>
            <div className="flex flex-col gap-1.5">
                <Label htmlFor="current_password">Contraseña actual</Label>
                <PasswordInput
                    id="current_password"
                    ref={currentPasswordInput}
                    name="current_password"
                    autoComplete="current-password"
                />
                <InputError message={errors['current_password']} />
            </div>
            <div className="flex flex-col gap-1.5">
                <Label htmlFor="password">Nueva contraseña</Label>
                <PasswordInput
                    id="password"
                    ref={passwordInput}
                    name="password"
                    autoComplete="new-password"
                    passwordrules={passwordRules}
                    aria-describedby="password-checklist"
                    onChange={(event) => onPasswordChange(event.target.value)}
                />
                <PasswordChecklist rules={passwordRules} value={password} />
                <InputError message={errors['password']} />
            </div>
            <div className="flex flex-col gap-1.5">
                <Label htmlFor="password_confirmation">
                    Confirmar contraseña
                </Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autoComplete="new-password"
                    passwordrules={passwordRules}
                />
                <InputError message={errors['password_confirmation']} />
            </div>
        </>
    );
}
