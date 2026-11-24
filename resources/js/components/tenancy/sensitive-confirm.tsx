import { ShieldAlert } from 'lucide-react';
import InputError from '@/components/humetria/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/**
 * Before saving permissions that open confidential data (spec: «advertencia en los sensibles»; board Roles 7c): exactly which
 * ones open, and the owner's confirmation, which the server asks for (`acknowledge_sensitive`). Nothing while none is added.
 */
export default function SensitiveConfirm({
    labels,
    copy,
    checked,
    onChange,
    error,
}: {
    labels: string[];
    /** «Este rol abre información sensible» and «Entiendo que quienes tengan este rol verán…». */
    copy: { title: string; consent: string };
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
            className="flex flex-col gap-2.5 rounded-xl bg-warning p-4 text-meta text-warning-foreground"
        >
            <p className="flex items-center gap-2 font-semibold">
                <ShieldAlert aria-hidden className="size-5 shrink-0" />
                {copy.title}
            </p>
            <p>{labels.join(' · ')}</p>
            <div className="flex items-start gap-2.5">
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
                    className="mt-0.5 bg-card"
                />
                <Label
                    htmlFor="acknowledge-sensitive"
                    className="leading-5 font-normal"
                >
                    {copy.consent}
                </Label>
            </div>
            <InputError id="acknowledge-sensitive-error" message={error} />
        </div>
    );
}
