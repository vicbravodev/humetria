/** Questions to follow an answer, as chips: in one row that scrolls sideways on a phone. */
export default function HumiSuggestions({
    suggestions,
    onPick,
}: {
    suggestions: readonly string[];
    onPick: (text: string) => void;
}) {
    return (
        <div className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 sm:flex-wrap sm:overflow-visible">
            {suggestions.map((text) => (
                <button
                    key={text}
                    type="button"
                    onClick={() => onPick(text)}
                    className="inline-flex min-h-11 shrink-0 items-center rounded-full border border-input bg-card px-3 text-sm whitespace-nowrap hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring xl:min-h-9"
                >
                    {text}
                </button>
            ))}
        </div>
    );
}
