/** A part HR writes, kept as written (line breaks included), or a note while it is still to be filled in. */
export default function ReportText({ text }: { text: string | null }) {
    if (text === null) {
        return <p className="text-muted-foreground">Pendiente de capturar.</p>;
    }

    return <p className="whitespace-pre-line">{text}</p>;
}
