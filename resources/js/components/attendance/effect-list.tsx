import { Check, CircleAlert, Info, TriangleAlert } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { EffectLine } from '@/lib/attendance/correction-effects';
import { cn } from '@/lib/utils';

const ICONS: Record<EffectLine['tone'], LucideIcon> = {
    done: Check,
    warn: TriangleAlert,
    info: Info,
    block: CircleAlert,
};

/** What a decision does, a line each: the things that happen with a check, the ones to know as a note, and what blocks it. */
export default function EffectList({ lines }: { lines: EffectLine[] }) {
    return (
        <ul className="flex flex-col gap-3">
            {lines.map(({ tone, text }) => {
                const Icon = ICONS[tone];

                return tone === 'done' ? (
                    <li key={text} className="flex items-start gap-3">
                        <Icon
                            aria-hidden
                            className="mt-0.5 size-5 shrink-0 text-primary"
                        />
                        <span>{text}</span>
                    </li>
                ) : (
                    <li
                        key={text}
                        role={tone === 'block' ? 'alert' : undefined}
                        className={cn(
                            'flex items-start gap-2.5 rounded-lg px-3.5 py-3 text-sm',
                            tone === 'block' &&
                                'bg-risk-alto-bg text-risk-alto-fg',
                            tone === 'warn' &&
                                'bg-warning text-warning-foreground',
                            tone === 'info' && 'bg-muted',
                        )}
                    >
                        <Icon aria-hidden className="mt-0.5 size-4 shrink-0" />
                        <span>{text}</span>
                    </li>
                );
            })}
        </ul>
    );
}
