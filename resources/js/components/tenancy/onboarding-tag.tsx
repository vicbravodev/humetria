import type { Tag } from '@/lib/onboarding';
import { cn } from '@/lib/utils';

const TONES: Record<Tag['tone'], string> = {
    yes: 'bg-accent text-primary',
    no: 'bg-muted text-muted-foreground',
    pending: 'border border-dashed border-input text-muted-foreground',
};

/** Whether a standard applies to a center, in the summary of the setup (board V3-Onboarding). */
export default function OnboardingTag({ tag }: { tag: Tag }) {
    return (
        <span
            className={cn(
                'inline-flex h-6 shrink-0 items-center rounded-full px-2.5 text-sm font-medium',
                TONES[tag.tone],
            )}
        >
            {tag.label}
        </span>
    );
}
