import { ArrowRight } from 'lucide-react';
import type { Starter } from '@/lib/assistant/suggestions';

/** The first questions, as a list with lines between them; under each, its live fact when one came. */
export default function HumiStarters({
    starters,
    onPick,
}: {
    starters: readonly Starter[];
    onPick: (text: string) => void;
}) {
    return (
        <ul className="flex flex-col">
            {starters.map((starter) => (
                <li key={starter.text} className="last:border-b">
                    <button
                        type="button"
                        onClick={() => onPick(starter.text)}
                        className="flex min-h-11 w-full items-center gap-3 border-t px-1 py-3.5 text-left hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        <span className="min-w-0 flex-1">
                            <span className="block text-base font-medium">
                                {starter.text}
                            </span>
                            {starter.live !== null && (
                                <small className="block text-sm text-muted-foreground">
                                    {starter.live}
                                </small>
                            )}
                        </span>
                        <ArrowRight
                            aria-hidden
                            className="size-5 shrink-0 text-muted-foreground"
                        />
                    </button>
                </li>
            ))}
        </ul>
    );
}
