import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export type Tone = 'accent' | 'warning' | 'blocked' | 'ok';

const TONES: Record<Tone, string> = {
    accent: 'bg-accent text-primary',
    warning: 'bg-warning text-warning-foreground',
    blocked: 'bg-risk-alto-bg text-risk-alto-fg',
    ok: 'bg-risk-bajo-bg text-risk-bajo-fg',
};

/** An icon on its soft square, in the tone of what it says (boards P1 to P5): accent, a warning, a block or done. */
export default function ToneTile({
    icon: Icon,
    tone = 'accent',
    large = false,
}: {
    icon: LucideIcon;
    tone?: Tone;
    large?: boolean;
}) {
    return (
        <span
            aria-hidden
            className={cn(
                'flex shrink-0 items-center justify-center rounded-xl',
                large ? 'size-16' : 'size-12',
                TONES[tone],
            )}
        >
            <Icon className={large ? 'size-8' : 'size-6'} />
        </span>
    );
}
