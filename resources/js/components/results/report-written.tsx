/** A part HR writes, kept as written (line breaks included), or what to write while it is still missing. */
export default function ReportWritten({
    text,
    hint,
}: {
    text: string | null;
    hint: string;
}) {
    return text === null ? (
        <p className="text-muted-foreground">{hint}</p>
    ) : (
        <p className="whitespace-pre-line">{text}</p>
    );
}
