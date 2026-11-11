import { Checkbox } from '@/components/ui/checkbox';

/** A tick to choose a row (or all of them), on a 44 px label so a finger hits it. */
export default function ChooseBox({
    checked,
    onChange,
    name,
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
    /** What it chooses, said to a screen reader: «Elegir a Ana López». */
    name: string;
}) {
    return (
        <label className="-m-3 flex size-11 cursor-pointer items-center justify-center">
            <Checkbox
                checked={checked}
                onCheckedChange={(next) => onChange(next === true)}
                aria-label={name}
            />
        </label>
    );
}
