import { Link } from '@inertiajs/react';
import { ListChecks, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { CenterNextStepData } from '@/types';

/** What a center lacks before it can start: its Comisión, then a published list (criterio C-17), with the way there. */
export default function CenterCenterNextStepData({
    step,
}: {
    step: CenterNextStepData;
}) {
    const Icon = step.kind === 'committee' ? Users : ListChecks;

    return (
        <section
            data-slot="empty-state"
            aria-labelledby="next-step-title"
            className="flex flex-col items-center rounded-xl border border-dashed bg-card px-6 py-10 text-center"
        >
            <span className="mb-4 flex size-12 items-center justify-center rounded-xl bg-accent text-primary">
                <Icon aria-hidden className="size-6" />
            </span>
            <h2
                id="next-step-title"
                className="text-xl leading-7 font-semibold tracking-[-0.01em]"
            >
                {step.title}
            </h2>
            <p className="mt-1 max-w-md text-muted-foreground">{step.body}</p>
            {step.href !== null && (
                <Button className="mt-5" asChild>
                    <Link href={step.href}>{step.cta}</Link>
                </Button>
            )}
        </section>
    );
}
