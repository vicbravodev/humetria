import { useState } from 'react';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { SatOption } from '@/types';

const NONE = 'none';

/** An optional field that takes one entry of a SAT catalog: what is stored is its code, what is read is its name. */
export default function AccountCatalogField({
    name,
    label,
    options,
    defaultValue,
    error,
}: {
    name: string;
    label: string;
    options: SatOption[];
    defaultValue: string;
    error: string | undefined;
}) {
    // A value saved before the catalog (free text) is not an option: the field starts empty so it is chosen again.
    const known = options.some((option) => option.value === defaultValue);
    const [value, setValue] = useState(known ? defaultValue : NONE);
    const id = `billing-${name}`;

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <input
                type="hidden"
                name={name}
                value={value === NONE ? '' : value}
            />
            <Select value={value} onValueChange={setValue}>
                <SelectTrigger
                    id={id}
                    className="w-full"
                    aria-invalid={error === undefined ? undefined : true}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={NONE}>Sin indicar</SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}
