/**
 * The id of the request that failed (X-Request-Id, in every log line of it), for the person to quote to support. Said
 * without "tú" or "usted": it shows in the panel and on the survey alike.
 */
export default function RequestIdNote({
    requestId,
}: {
    requestId: string | null;
}) {
    if (requestId === null) {
        return null;
    }

    return (
        <span data-slot="request-id" className="mt-3 block text-sm">
            Código para soporte:{' '}
            <code className="font-mono break-all text-foreground select-all">
                {requestId}
            </code>
        </span>
    );
}
