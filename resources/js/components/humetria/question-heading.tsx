/** The top of a question (boards V2-Politica and V3-Doc-Riesgos): its topic, the question as the page's title and a hint. */
export default function QuestionHeading({
    topic,
    title,
    hint,
}: {
    topic: string;
    title: string;
    hint: string | null;
}) {
    return (
        <div>
            <p className="mb-1.5 text-sm font-semibold text-primary">{topic}</p>
            <h1 className="text-2xl leading-8 font-semibold tracking-[-0.01em] text-balance md:text-[26px] md:leading-[34px]">
                {title}
            </h1>
            {hint !== null && (
                <p className="mt-2 text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}
