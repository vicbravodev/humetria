import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import type { OptionSource } from '@/hooks/use-option-search';
import type { Option } from '@/lib/option-search';

/** A labelled choice from a list, with its error under it. `onChange` follows the choice for a field others depend on. */
export default function ChoiceField({
    name,
    label,
    source,
    defaultValue,
    error,
    placeholder = 'Elige uno',
    onChange,
}: {
    name: string;
    label: string;
    source: OptionSource;
    defaultValue: Option | undefined;
    error: string | undefined;
    placeholder?: string;
    onChange?: (option: Option) => void;
}) {
    const id = `benefit-${name}`;

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Combobox
                id={id}
                name={name}
                source={source}
                defaultValue={defaultValue}
                placeholder={placeholder}
                invalid={error !== undefined}
                describedBy={error === undefined ? undefined : `${id}-error`}
                {...(onChange === undefined ? {} : { onChange })}
            />
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}
