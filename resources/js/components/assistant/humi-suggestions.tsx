/** Questions to follow an answer, as chips. */
export default function HumiSuggestions({
    suggestions,
    onPick,
}: {
    suggestions: readonly string[];
    onPick: (text: string) => void;
}) {
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
