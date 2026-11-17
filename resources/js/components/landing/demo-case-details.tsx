import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import type { DemoChoice } from '@/components/landing/demo-choice-field';
import DemoChoiceField from '@/components/landing/demo-choice-field';
import DemoMessageField from '@/components/landing/demo-message-field';
import DemoToolsField from '@/components/landing/demo-tools-field';

const PROFILES: readonly DemoChoice[] = [
    { value: 'company', label: 'Para mi empresa o grupo de empresas' },
    { value: 'consultant', label: 'Soy consultor y atiendo a varias empresas' },
    { value: 'union', label: 'Represento a un sindicato' },
];

const WORKFORCE: readonly DemoChoice[] = [
    { value: '1_15', label: '1–15' },
    { value: '16_50', label: '16–50' },
    { value: '51_100', label: '51–100' },
    { value: '101_500', label: '101–500' },
    { value: '501_plus', label: 'Más de 500' },
];

/** The case details are optional: folded away, so the form is four fields until the visitor wants to say more. */
export default function DemoCaseDetails({
    errors,
}: {
    errors: Record<string, string>;
}) {
    const [open, setOpen] = useState(false);
    const failed = ['profile', 'workforce', 'tools', 'message'].some(
        (field) => errors[field] !== undefined,
    );

    return (
        <details
            open={open || failed}
            onToggle={(event) => setOpen(event.currentTarget.open)}
            data-test="demo-case"
            className="group grid gap-[18px]"
        >
            <summary
                data-test="demo-case-toggle"
                className="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 rounded-lg border border-input px-4 py-2.5 text-meta font-medium transition-colors duration-150 ease-out marker:hidden hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden"
            >
                <span>
                    Cuéntanos tu caso{' '}
                    <span className="font-normal text-muted-foreground">
                        (opcional)
                    </span>
                </span>
                <ChevronDown
                    aria-hidden
                    className="size-5 shrink-0 text-muted-foreground transition-transform duration-200 ease-out group-open:rotate-180"
                />
            </summary>
            <div className="grid gap-[18px] pt-1.5">
                <DemoChoiceField
                    name="profile"
                    legend="¿Para quién es Humetría?"
                    options={PROFILES}
                    error={errors['profile']}
                />
                <DemoToolsField error={errors['tools']} />
                <DemoChoiceField
                    name="workforce"
                    legend="¿Cuántas personas trabajan en la empresa?"
                    options={WORKFORCE}
                    error={errors['workforce']}
                />
                <DemoMessageField error={errors['message']} />
            </div>
        </details>
    );
}
