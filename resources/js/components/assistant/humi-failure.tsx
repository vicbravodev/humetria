import { CircleAlert } from 'lucide-react';
import type { ChatFailure } from '@/lib/assistant/chat-errors';

/** What the panel says when a message did not get an answer: Humi's own words for a refusal, a calm line for the rest. */
export default function HumiFailure({
    failure,
    onRetry,
}: {
    failure: ChatFailure;
    onRetry: () => void;
}) {
    const refused = failure.reason !== 'error';

    return (
        <div role="alert" className="flex flex-col gap-2">
            {refused ? (
                <p className="rounded-lg bg-accent px-3.5 py-3 text-base">
                    {failure.message}
                </p>
            ) : (
                <p className="flex items-center justify-end gap-2 text-sm">
                    <CircleAlert
                        aria-hidden
                        className="size-4 text-destructive"
                    />
                    <span className="text-destructive">No se envió</span>
                    <button
                        type="button"
                        onClick={onRetry}
                        className="font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        Reintentar
                    </button>
                </p>
            )}
        </div>
    );
}
