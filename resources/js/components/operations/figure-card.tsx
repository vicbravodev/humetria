/** A figure of the platform: what it counts, the number and a sentence that puts it in context. */
export default function FigureCard({
    title,
    figure,
    caption,
}: {
    title: string;
    figure: string;
    caption: string;
}) {
    return (
        <section className="grid content-start gap-2 card p-5">
            <h2 className="text-meta font-medium text-muted-foreground">
                {title}
            </h2>
            <p className="text-3xl font-semibold tracking-[-0.01em] tabular-nums">
                {figure}
            </p>
            <p className="text-sm text-muted-foreground">{caption}</p>
        </section>
    );
}
