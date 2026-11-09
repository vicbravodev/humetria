import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/** A yes-or-no of the policy: checked sends «1», left off sends nothing, which the server takes as no. */
export default function PolicySwitch({
    name,
    label,
    hint,
    checked,
}: {
    name: string;
    label: string;
    hint: string;
    checked: boolean;
}) {
    return (
        <div className="flex items-start gap-3 rounded-lg border bg-card p-4">
            <Checkbox
                id={`policy-${name}`}
                name={name}
                value="1"
                defaultChecked={checked}
                className="mt-0.5"
            />
            <div className="grid gap-1">
                <Label htmlFor={`policy-${name}`}>{label}</Label>
                <p className="text-sm text-pretty text-muted-foreground">
                    {hint}
                </p>
            </div>
        </div>
    );
}
