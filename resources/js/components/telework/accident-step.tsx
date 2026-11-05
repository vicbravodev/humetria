import { Check } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** One of the three steps to follow up an accident (board V3-Aviso): its number, or a check once done, and what it asks. */
export default function AccidentStep({
    number,
    state,
    title,
    text,
    children,
}: {
    number: number;
    state: 'done' | 'now' | 'next';
    title: string;
    text: string;
    children: ReactNode;
}) {
    return (
        <div className="grid grid-cols-[36px_minmax(0,1fr)] gap-4 border-t px-5 py-5 md:px-6">
            <span
                aria-hidden
                className={cn(
                    'inline-flex size-9 items-center justify-center rounded-full text-sm font-semibold',
                    state === 'done' && 'bg-primary text-primary-foreground',
                    state === 'now' && 'border-2 border-primary text-primary',
                    state === 'next' &&
                        'border-[1.5px] border-input text-muted-foreground',
                )}
            >
                {state === 'done' ? <Check className="size-4" /> : number}
            </span>
            <div className="flex min-w-0 flex-col gap-3.5">
                <div>
                    <h2 className="font-semibold">{title}</h2>
                    <p className="text-sm text-muted-foreground">{text}</p>
                </div>
                {children}
            </div>
        </div>
    );
}
