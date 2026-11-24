import { ShieldAlert } from 'lucide-react';
import InputError from '@/components/humetria/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/**
 * Before saving permissions that open confidential data (spec: «advertencia en los sensibles»): which ones, what they open, and
 * the owner's «Entiendo y confirmo», which the server asks for (`acknowledge_sensitive`). Nothing while none is added.
 */
export default function SensitiveConfirm({
    labels,
    checked,
    onChange,
    error,
}: {
    labels: string[];
    checked: boolean;
    onChange: (checked: boolean) => void;
    error: string | undefined;
}) {
    if (labels.length === 0) {
        return null;
    }

    return (
        <div
            role="status"
            className="flex flex-col gap-3 rounded-xl bg-warning px-5 py-4 text-warning-foreground"
        >
            <div className="flex gap-3">
                <ShieldAlert aria-hidden className="mt-0.5 size-5 shrink-0" />
                <div className="flex flex-col gap-1 text-meta">
                    <p className="font-semibold">
                        Vas a dar permisos que abren información sensible:{' '}
                        {labels.join(', ')}.
                    </p>
                    <p>
                        Quien los tenga verá datos personales y confidenciales.
                        Cada vez que los abra queda registrado quién y cuándo.
                    </p>
                </div>
            </div>
            <div className="flex items-center gap-2.5 pl-8">
                <Checkbox
                    id="acknowledge-sensitive"
                    checked={checked}
                    onCheckedChange={(value) => onChange(value === true)}
                    aria-invalid={error === undefined ? undefined : true}
                    aria-describedby={
                        error === undefined
                            ? undefined
                            : 'acknowledge-sensitive-error'
                    }
                />
                <Label htmlFor="acknowledge-sensitive">
                    Entiendo y confirmo
                </Label>
            </div>
            <InputError id="acknowledge-sensitive-error" message={error} />
        </div>
    );
}
