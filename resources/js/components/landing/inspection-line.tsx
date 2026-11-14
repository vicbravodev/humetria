import { Check, CircleAlert } from 'lucide-react';
import { enterDelay } from '@/lib/enter-delay';
import { cn } from '@/lib/utils';

type Props = {
    text: string;
    tool: string;
    done: boolean;
    index: number;
};

/** One piece of the file: ready, or what it still lacks, with the tool it comes from. */
export default function InspectionLine({ text, tool, done, index }: Props) {
    return (
        <li
            data-reveal="idle"
            className={cn(
                'grid grid-cols-[28px_minmax(0,1fr)] items-start gap-3 border-b px-5 py-3.5 reveal-slide-10 last:border-b-0',
                !done && 'bg-risk-medio-bg/55',
                enterDelay(index * 0.08),
            )}
        >
            <span
                className={cn(
                    'mt-px flex size-6 items-center justify-center rounded-full',
                    done
                        ? 'bg-primary text-primary-foreground'
                        : 'border-[1.5px] border-risk-medio bg-risk-medio-bg text-risk-medio-fg',
                )}
            >
                {done ? (
                    <Check aria-hidden className="size-3.5" />
                ) : (
                    <CircleAlert aria-hidden className="size-3.5" />
                )}
                <span className="sr-only">
                    {done ? 'Listo:' : 'Pendiente:'}
                </span>
            </span>
            <span className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                <span className="text-meta leading-snug">{text}</span>
                <span
                    className={cn(
                        'shrink-0 rounded-full px-2 py-1 text-xs leading-none font-medium',
                        done
                            ? 'bg-accent text-primary'
                            : 'bg-risk-medio-bg text-risk-medio-fg',
                    )}
                >
                    {tool}
                </span>
            </span>
        </li>
    );
}
