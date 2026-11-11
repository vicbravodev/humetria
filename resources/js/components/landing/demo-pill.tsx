import { Check } from 'lucide-react';

type Props = {
    type: 'radio' | 'checkbox';
    name: string;
    value: string;
    label: string;
};

/** One choice of the demo form as a pill: the whole pill is the control, and a check shows on the chosen ones. */
export default function DemoPill({ type, name, value, label }: Props) {
    return (
        <label className="group relative inline-flex min-h-12 cursor-pointer items-center gap-2 rounded-xl border border-input bg-background px-4 text-meta leading-tight transition-[background-color,border-color,color,transform] duration-150 ease-out active:scale-[0.97] has-checked:border-primary has-checked:bg-primary has-checked:text-primary-foreground has-focus-visible:outline-2 has-focus-visible:outline-offset-3 has-focus-visible:outline-ring motion-reduce:active:scale-100">
            <input
                type={type}
                name={name}
                value={value}
                className="absolute inset-0 m-0 size-full cursor-pointer opacity-0"
            />
            <Check
                aria-hidden
                className="hidden size-4 group-has-checked:block"
            />
            {label}
        </label>
    );
}
