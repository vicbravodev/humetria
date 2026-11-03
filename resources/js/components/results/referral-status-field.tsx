import InputError from '@/components/humetria/input-error';
import type { ReferralStatus } from '@/types';

const CHOICES: { value: ReferralStatus; label: string }[] = [
    { value: 'referred', label: 'Canalizada' },
    { value: 'attended', label: 'Atendida' },
];

/** What happened to the person: they were referred to care, or they were already attended. */
export default function ReferralStatusField({
    defaultValue,
    error,
}: {
    defaultValue: ReferralStatus;
    error: string | undefined;
}) {
    return (
        <fieldset className="grid gap-2 sm:grid-cols-2">
            <legend className="mb-1 text-sm font-medium">Estado</legend>
            {CHOICES.map((choice) => (
                <label
                    key={choice.value}
                    className="flex min-h-11 cursor-pointer items-center gap-3 rounded-md border border-input bg-card px-4 has-[:checked]:border-primary has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-ring"
                >
                    <input
                        type="radio"
                        name="status"
                        value={choice.value}
                        defaultChecked={choice.value === defaultValue}
                        className="size-5 accent-primary"
                    />
                    {choice.label}
                </label>
            ))}
            <InputError message={error} />
        </fieldset>
    );
}
