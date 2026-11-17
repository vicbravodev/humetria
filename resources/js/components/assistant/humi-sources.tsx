/** Where the figures of an answer came from, numbered, as the tool that answered said. */
export default function HumiSources({
    sources,
}: {
    sources: readonly string[];
}) {
    return (
        <div className="flex flex-wrap items-center gap-1.5">
            <span className="mr-0.5 text-caption text-muted-foreground">
                Fuentes
            </span>
            <ol aria-label="Fuentes" className="contents">
                {sources.map((source, index) => (
                    <li
                        key={source}
                        className="inline-flex min-h-7 items-center gap-1.5 rounded-md border px-2.5 text-caption font-medium text-muted-foreground"
                    >
                        <span className="text-primary">{index + 1}</span>
                        {source}
                    </li>
                ))}
            </ol>
        </div>
    );
}
