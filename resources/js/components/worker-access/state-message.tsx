import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import type { Tone } from '@/components/humetria/tone-tile';
import { cn } from '@/lib/utils';

const TONES: Record<Tone, string> = {
    accent: 'bg-accent text-primary',
    warning: 'bg-warning text-warning-foreground',
    blocked: 'bg-risk-alto-bg text-risk-alto-fg',
    ok: 'bg-risk-bajo-bg text-risk-bajo-fg',
};

/**
 * What a screen of state says (a link that no longer works, a key that is done, nothing pending): an 80 px icon that comes in
 * (fade and a scale from 0.96 in 200 ms; only the fade with reduced motion), the title and the text, centered. Goes in a
 * `centered` frame, with its one action stuck to the bottom.
 */
export default function StateMessage({
    icon: Icon,
    tone = 'accent',
    title,
    role,
    children,
}: {
    icon: LucideIcon;
    tone?: Tone;
    title: string;
    /** `alert` for a problem, `status` for news the person waited for. */
    role?: 'alert' | 'status';
    children: ReactNode;
}) {
    return (
        <section
            role={role}
            data-slot="state-message"
            className="flex flex-col items-center gap-4 text-center"
        >
            <span
                aria-hidden
                className={cn(
                    'flex size-20 shrink-0 animate-in items-center justify-center rounded-2xl duration-200 ease-out fade-in zoom-in-96',
                    TONES[tone],
                )}
            >
                <Icon className="size-10" />
            </span>
            <h1 className="text-2xl leading-8 font-semibold tracking-tight text-balance">
                {title}
            </h1>
            <div className="max-w-xs text-muted-foreground">{children}</div>
        </section>
    );
}
