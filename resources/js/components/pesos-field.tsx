import { useState } from 'react';
import InputError from '@/components/humetria/input-error';
import {
    InputGroup,
    InputGroupInput,
    InputGroupText,
} from '@/components/ui/input-group';
import { Label } from '@/components/ui/label';
import { formatPesos, rawPesos } from '@/lib/pesos-input';

/**
 * An amount in Mexican pesos: it is written as pesos are, «15,000.00», when the field is left, and edited as a plain number
 * while it has the focus. The form sends the plain number (`name`, in a hidden field): the server never sees the commas.
 */
export default function PesosField({
    name,
    label,
    idPrefix = 'field',
    defaultValue = '',
    placeholder,
    error,
}: {
    name: string;
    label: string;
    idPrefix?: string;
    /** The plain amount it starts with: "15000". */
    defaultValue?: string;
    placeholder?: string;
    error?: string | undefined;
}) {
    const id = `${idPrefix}-${name}`;
    const errorId = `${id}-error`;
    const [text, setText] = useState(
        () => formatPesos(defaultValue) ?? defaultValue,
    );

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <InputGroup>
                <InputGroupText>$</InputGroupText>
                <InputGroupInput
                    id={id}
                    value={text}
                    inputMode="decimal"
                    autoComplete="off"
                    {...(placeholder === undefined ? {} : { placeholder })}
                    aria-invalid={error === undefined ? undefined : true}
                    aria-describedby={error === undefined ? undefined : errorId}
                    onChange={(event) => setText(event.target.value)}
                    onFocus={() => setText((current) => rawPesos(current))}
                    onBlur={() =>
                        setText(
                            (current) =>
                                formatPesos(rawPesos(current)) ?? current,
                        )
                    }
                />
                <InputGroupText>MXN</InputGroupText>
            </InputGroup>
            <input type="hidden" name={name} value={rawPesos(text)} />
            <InputError id={errorId} message={error} />
        </div>
    );
}
