import { ChevronDown } from 'lucide-react';
import { InputGroup, InputGroupInput } from '@/components/ui/input-group';
import { usePhoneField } from '@/hooks/use-phone-field';
import { DEFAULT_COUNTRY } from '@/lib/phone';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    /** The form field: it submits the number in E.164 (`+525512345678`), or what was typed when it is none. */
    name: string;
    /** A number stored in E.164: the field opens on its country. */
    defaultValue?: string | null | undefined;
    required?: boolean | undefined;
    invalid?: boolean | undefined;
    describedBy?: string | undefined;
    autoComplete?: string | undefined;
    /** For a taller box, as the landing's form has. */
    className?: string | undefined;
};

/**
 * A phone field of any country, Mexico first: the country's flag and code over a native select (searchable by
 * typing, the phone's own picker on a mobile), and the number masked as it is written there while it is typed.
 * A number pasted however (with +52, the old 044, another country's code) is read.
 */
export default function PhoneInput(props: Props) {
    const { input, phone, countries, flag, type, choose } = usePhoneField(
        props.defaultValue,
    );
    const prefix = countries.find((option) => option.code === phone.country);

    return (
        <InputGroup className={props.className}>
            <div className="relative flex h-full shrink-0 items-center gap-1.5 rounded-l-md border-r border-input pr-2 pl-3 has-[select:focus-visible]:outline-2 has-[select:focus-visible]:outline-offset-2 has-[select:focus-visible]:outline-ring">
                <span className="flex h-4 w-6 shrink-0 overflow-hidden rounded-[3px] bg-muted shadow-[0_0_0_1px_var(--border)]">
                    {flag !== undefined && (
                        <img src={flag} alt="" className="size-full" />
                    )}
                </span>
                <span
                    data-test="phone-prefix"
                    className="text-base text-muted-foreground tabular-nums"
                >
                    {prefix?.prefix}
                </span>
                <ChevronDown
                    aria-hidden
                    className="size-3.5 text-muted-foreground"
                />
                <select
                    aria-label="País del teléfono"
                    value={phone.country}
                    onChange={(event) => choose(event.target.value)}
                    className="absolute inset-0 cursor-pointer appearance-none opacity-0"
                >
                    {countries.map((option) => (
                        <option key={option.code} value={option.code}>
                            {option.name === ''
                                ? option.prefix
                                : `${option.name} (${option.prefix})`}
                        </option>
                    ))}
                </select>
            </div>
            <InputGroupInput
                ref={input}
                id={props.id}
                type="tel"
                inputMode="tel"
                autoComplete={props.autoComplete ?? 'tel'}
                required={props.required}
                placeholder={
                    phone.country === DEFAULT_COUNTRY ? '55 1234 5678' : ''
                }
                value={phone.display}
                onChange={(event) => type(event.target.value)}
                aria-invalid={props.invalid === true || undefined}
                aria-describedby={props.describedBy}
                className={cn('pl-3', props.className && 'text-base')}
            />
            <input type="hidden" name={props.name} value={phone.value} />
        </InputGroup>
    );
}
