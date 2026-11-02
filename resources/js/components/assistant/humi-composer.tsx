import { ArrowUp, Square } from 'lucide-react';
import { useState, type KeyboardEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

/**
 * Where the person writes: it grows up to six lines, Enter sends and Shift + Enter breaks the line. While an answer comes
 * the send button becomes Detener. When Humi cannot answer (limit, off) it is closed and says why.
 */
export default function HumiComposer({
    streaming,
    closedText,
    onSend,
    onStop,
}: {
    streaming: boolean;
    /** Why the person cannot write now; null while they can. */
    closedText: string | null;
    onSend: (text: string) => void;
    onStop: () => void;
}) {
    const [text, setText] = useState('');
    const closed = closedText !== null;

    function send() {
        if (text.trim() !== '' && !streaming && !closed) {
            onSend(text.trim());
            setText('');
        }
    }

    function onKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
        if (
            event.key === 'Enter' &&
            !event.shiftKey &&
            !event.nativeEvent.isComposing
        ) {
            event.preventDefault();
            send();
        }
    }

    return (
        <div className="flex flex-col gap-2 border-t p-4">
            <div className="flex items-end gap-2 rounded-lg border border-input bg-card p-1.5 pl-3.5 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-ring">
                <Textarea
                    value={text}
                    rows={1}
                    disabled={closed}
                    aria-label="Pregúntale algo a Humi"
                    placeholder={
                        closedText ??
                        (streaming
                            ? 'Humi está respondiendo'
                            : 'Pregúntale algo a Humi')
                    }
                    onChange={(event) => setText(event.target.value)}
                    onKeyDown={onKeyDown}
                    className="[field-sizing:content] max-h-36 min-h-11 flex-1 resize-none rounded-none border-0 bg-transparent px-0 py-2 hover:border-0 focus-visible:outline-0 disabled:bg-transparent"
                />
                {streaming ? (
                    <Button
                        type="button"
                        size="icon"
                        aria-label="Detener"
                        onClick={onStop}
                        className="size-11 bg-foreground text-background"
                    >
                        <Square aria-hidden className="size-3.5 fill-current" />
                    </Button>
                ) : (
                    <Button
                        type="button"
                        size="icon"
                        aria-label="Enviar"
                        disabled={closed || text.trim() === ''}
                        onClick={send}
                        className="size-11"
                    >
                        <ArrowUp aria-hidden />
                    </Button>
                )}
            </div>
            <p className="text-center text-caption text-muted-foreground">
                Humi puede equivocarse. Verifica las cifras antes de
                reportarlas.
            </p>
        </div>
    );
}
