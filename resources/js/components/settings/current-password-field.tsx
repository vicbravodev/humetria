import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** The password that proves who is changing the account's email. */
export default function CurrentPasswordField({
    error,
}: {
    error: string | undefined;
}) {
    return (
        <div className="flex flex-col gap-1.5">
            <Label htmlFor="current_password">Contraseña actual</Label>
            <Input
                id="current_password"
                type="password"
                name="current_password"
                autoComplete="current-password"
                aria-describedby="current-password-help"
            />
            <p
                id="current-password-help"
                className="text-caption text-muted-foreground"
            >
                Solo si cambias el correo electrónico.
            </p>
            <InputError message={error} />
        </div>
    );
}
