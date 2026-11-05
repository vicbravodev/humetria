import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/** A yes/no attribute of a benefit, with the sentence that says what yes does. */
export default function CheckField({
    name,
    label,
    defaultChecked,
}: {
    name: string;
    label: string;
    defaultChecked: boolean;
}) {
    const id = `benefit-${name}`;

    return (
        <div className="flex items-start gap-3">
            <Checkbox
                id={id}
                name={name}
                defaultChecked={defaultChecked}
                className="mt-0.5"
            />
            <Label htmlFor={id} className="leading-5 font-normal">
                {label}
            </Label>
        </div>
    );
}
