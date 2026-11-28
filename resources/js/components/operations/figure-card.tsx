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
        <section className="grid content-start gap-1 card p-6">
            <h2 className="text-caption font-medium tracking-[0.02em] text-muted-foreground uppercase">
                {title}
            </h2>
            <p className="text-[32px] leading-10 font-semibold tracking-[-0.01em] tabular-nums">
                {figure}
            </p>
            <p className="text-sm text-muted-foreground">{caption}</p>
        </section>
    );
}
