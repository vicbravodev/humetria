import { Check, Copy, RefreshCw, ThumbsDown, ThumbsUp } from 'lucide-react';
import { useState } from 'react';
import { useClipboard } from '@/hooks/use-clipboard';
import type { Rating } from '@/lib/assistant/api';
import { cn } from '@/lib/utils';

const BUTTON =
    'flex size-11 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring';

/** What can be done with a finished answer: copy it, give it a thumb up or down, or ask again. */
export default function HumiMessageActions({
    text,
    rating,
    canRate,
    onRate,
    onRegenerate,
}: {
    text: string;
    rating: Rating | null;
    canRate: boolean;
    onRate: (rating: Rating) => void;
    onRegenerate: (() => void) | null;
}) {
    const [, copy] = useClipboard();
    const [copied, setCopied] = useState(false);

    return (
        <div className="-ml-2 flex items-center gap-0.5">
            <button
                type="button"
                aria-label={copied ? 'Copiado' : 'Copiar respuesta'}
                className={BUTTON}
                onClick={() => void copy(text).then((done) => setCopied(done))}
            >
                {copied ? (
                    <Check aria-hidden className="size-4" />
                ) : (
                    <Copy aria-hidden className="size-4" />
                )}
            </button>
            {canRate && (
                <>
                    <button
                        type="button"
                        aria-label="Respuesta útil"
                        aria-pressed={rating === 'up'}
                        className={cn(
                            BUTTON,
                            rating === 'up' && 'text-primary',
                        )}
                        onClick={() => onRate('up')}
                    >
                        <ThumbsUp aria-hidden className="size-4" />
                    </button>
                    <button
                        type="button"
                        aria-label="Respuesta no útil"
                        aria-pressed={rating === 'down'}
                        className={cn(
                            BUTTON,
                            rating === 'down' && 'text-primary',
                        )}
                        onClick={() => onRate('down')}
                    >
                        <ThumbsDown aria-hidden className="size-4" />
                    </button>
                </>
            )}
            {onRegenerate !== null && (
                <button
                    type="button"
                    aria-label="Volver a responder"
                    className={BUTTON}
                    onClick={onRegenerate}
                >
                    <RefreshCw aria-hidden className="size-4" />
                </button>
            )}
        </div>
    );
}
