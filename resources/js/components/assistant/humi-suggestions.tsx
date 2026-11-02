import { ArrowRight } from 'lucide-react';

/** First questions to pick from, as a column of buttons (the empty panel) or as chips after an answer. */
export default function HumiSuggestions({
    suggestions,
    onPick,
    variant,
}: {
    suggestions: readonly string[];
    onPick: (text: string) => void;
    variant: 'list' | 'chips';
}) {
    if (variant === 'chips') {
        return (
            <div className="flex flex-wrap gap-2">
                {suggestions.map((text) => (
                    <button
                        key={text}
                        type="button"
                        onClick={() => onPick(text)}
                        className="inline-flex min-h-9 items-center rounded-full border border-input bg-card px-3 text-sm hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        {text}
                    </button>
                ))}
            </div>
        );
    }

    return (
        <ul className="flex w-full flex-col gap-2">
            {suggestions.map((text) => (
                <li key={text}>
                    <button
                        type="button"
                        onClick={() => onPick(text)}
                        className="flex min-h-11 w-full items-center justify-between gap-3 rounded-md border border-input bg-card px-4 text-left text-base hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        {text}
                        <ArrowRight
                            aria-hidden
                            className="size-4 shrink-0 text-muted-foreground"
                        />
                    </button>
                </li>
            ))}
        </ul>
    );
}
