import { useState } from 'react';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

/** The box where the person writes to who attends their case. */
export default function FollowUpReply({
    error,
    processing,
    onReply,
}: {
    error: string | undefined;
    processing: boolean;
    onReply: (body: string, onDone: () => void) => void;
}) {
    const [text, setText] = useState('');

    return (
        <form
            className="flex flex-col gap-3 card p-5"
            onSubmit={(event) => {
                event.preventDefault();
                onReply(text, () => setText(''));
            }}
        >
            <Label
                htmlFor="follow-up-reply"
                className="text-base font-semibold"
            >
                Escribir a quien atiende su caso
            </Label>
            <Textarea
                id="follow-up-reply"
                rows={4}
                value={text}
                onChange={(event) => setText(event.target.value)}
            />
            <InputError message={error} />
            <Button
                variant="outline"
                className="self-start"
                disabled={processing || text.trim() === ''}
            >
                {processing && <Spinner />}
                Enviar
            </Button>
        </form>
    );
}
