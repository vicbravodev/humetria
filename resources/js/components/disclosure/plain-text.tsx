/**
 * A text of the company as paragraphs (split by a blank line) of plain text: React escapes it, so nothing in it can become
 * markup on a page that anybody with a link reads (criterio C-51).
 */
export default function PlainText({ text }: { text: string }) {
    return (
        <div className="grid gap-3">
            {text
                .split(/\n{2,}/)
                .filter((paragraph) => paragraph !== '')
                .map((paragraph) => (
                    <p
                        key={paragraph}
                        className="text-pretty whitespace-pre-line"
                    >
                        {paragraph}
                    </p>
                ))}
        </div>
    );
}
