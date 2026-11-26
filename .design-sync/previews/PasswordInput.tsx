import { InputError, Label, PasswordInput } from 'humetria';

export const Login = () => (
    <div className="grid gap-2 max-w-xs">
        <Label htmlFor="password">Contraseña</Label>
        <PasswordInput id="password" name="password" autoComplete="current-password" defaultValue="Apodaca-2026" />
    </div>
);

export const Empty = () => (
    <div className="grid gap-2 max-w-xs">
        <Label htmlFor="confirm-password">Confirma tu contraseña</Label>
        <PasswordInput id="confirm-password" name="password" placeholder="Tu contraseña" />
    </div>
);

export const WithError = () => (
    <div className="grid gap-2 max-w-xs">
        <Label htmlFor="password-bad">Contraseña</Label>
        <PasswordInput id="password-bad" name="password" defaultValue="apodaca" aria-invalid aria-describedby="password-bad-error" />
        <InputError id="password-bad-error" message="Esa contraseña no coincide con tu cuenta." />
    </div>
);

export const Disabled = () => (
    <div className="grid gap-2 max-w-xs">
        <Label htmlFor="password-off">Contraseña</Label>
        <PasswordInput id="password-off" name="password" defaultValue="Apodaca-2026" disabled />
    </div>
);
