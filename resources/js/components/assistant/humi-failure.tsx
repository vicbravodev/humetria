import { Clock, RotateCw, TriangleAlert } from 'lucide-react';
import HumiAvatar from '@/components/assistant/humi-avatar';
import { Button } from '@/components/ui/button';
import { rateWaitText, type ChatFailure } from '@/lib/assistant/chat-errors';

/**
 * What the panel says when a message did not get an answer: when Humi is back after too many questions, Humi's own words
 * for the rest of the refusals, and for a fault what failed, a retry and the code for support, with Humi listening again.
 */
export default function HumiFailure({
    failure,
    requestId,
    onRetry,
}: {
    failure: ChatFailure;
    requestId: string | null;
    onRetry: () => void;
}) {
    if (failure.reason === 'rate') {
        return (
            <p
                role="alert"
                className="humi-enter flex items-start gap-2.5 rounded-lg bg-warning px-3.5 py-3 text-sm text-warning-foreground"
            >
                <Clock aria-hidden className="mt-0.5 size-4 shrink-0" />
                {rateWaitText(failure.retryAfter)}
            </p>
        );
    }

    if (failure.reason !== 'error') {
        return (
            <p
                role="alert"
                className="humi-enter rounded-lg bg-accent px-3.5 py-3 text-base"
            >
                {failure.message}
            </p>
        );
    }

    return (
        <div role="alert" className="humi-enter flex gap-2.5">
            <HumiAvatar size={28} expression="escucha" />
            <div className="flex min-w-0 flex-1 flex-col gap-2">
                <div className="flex flex-col items-start gap-3 rounded-lg border border-destructive bg-card p-4">
                    <p className="flex items-start gap-2.5 text-sm">
                        <TriangleAlert
                            aria-hidden
                            className="mt-0.5 size-4 shrink-0 text-destructive"
                        />
                        No pude responder esta vez.
                    </p>
                    <Button variant="outline" size="sm" onClick={onRetry}>
                        <RotateCw aria-hidden />
                        Reintentar
                    </Button>
                </div>
                {requestId !== null && (
                    <p className="text-caption text-muted-foreground">
                        Si pasa otra vez, escribe a soporte con este código:{' '}
                        <code className="font-mono break-all text-foreground select-all">
                            {requestId}
                        </code>
                    </p>
                )}
            </div>
        </div>
    );
}
