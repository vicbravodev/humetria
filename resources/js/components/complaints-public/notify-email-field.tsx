import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Optional (decisión A10): an address to be told that the complaint came and, later, that there is news — nothing else.
 * Nobody reads it, not even who handles the case, and it is erased after the case closes.
 */
export default function NotifyEmailField({
    value,
    onChange,
}: {
    value: string;
    onChange: (email: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="notify-email">
                Correo para avisarle{' '}
                <span className="font-normal text-muted-foreground">
                    Opcional
                </span>
            </Label>
            <Input
                id="notify-email"
                type="email"
                autoComplete="off"
                value={value}
                maxLength={190}
                aria-describedby="notify-email-hint"
                onChange={(event) => onChange(event.target.value)}
            />
            <p id="notify-email-hint" className="text-sm text-muted-foreground">
                Le escribimos solo que la recibimos y cuando haya novedades, sin
                la referencia ni nada de su queja. Nadie lo ve, ni quien la
                atiende, y lo borramos 30 días después de cerrarla. Un correo
                del trabajo puede delatarle: prefiera uno personal.
            </p>
        </div>
    );
}
