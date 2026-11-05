import { useState } from 'react';
import InputError from '@/components/humetria/input-error';
import PasswordInput from '@/components/humetria/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

/**
 * «Ver cómo va su queja» (board V4-Quejas-Seguimiento): the reference and the key the system gave (decisión A9), kept only
 * in this page's memory, and what losing the key means.
 */
export default function FollowUpForm({
    error,
    processing,
    onOpen,
}: {
    error: string | undefined;
    processing: boolean;
    onOpen: (reference: string, key: string) => void;
}) {
    const [reference, setReference] = useState('');
    const [key, setKey] = useState('');

    return (
        <form
            className="flex flex-col gap-5"
            onSubmit={(event) => {
                event.preventDefault();
                onOpen(reference, key);
            }}
        >
            <div className="flex flex-col gap-1.5">
                <h1 className="text-[26px] leading-8 font-semibold tracking-[-0.01em]">
                    Ver cómo va su queja
                </h1>
                <p className="text-muted-foreground">
                    Escriba la referencia y la clave que le dimos al
                    presentarla. No las guardamos en este dispositivo.
                </p>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="follow-up-reference">Referencia</Label>
                <Input
                    id="follow-up-reference"
                    value={reference}
                    autoComplete="off"
                    autoCapitalize="characters"
                    className="font-mono"
                    onChange={(event) => setReference(event.target.value)}
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="follow-up-key">Clave</Label>
                <PasswordInput
                    id="follow-up-key"
                    value={key}
                    autoComplete="off"
                    autoCapitalize="characters"
                    className="font-mono"
                    onChange={(event) => setKey(event.target.value)}
                />
            </div>
            <InputError message={error} />
            <Button
                className="w-full"
                disabled={
                    processing || reference.trim() === '' || key.trim() === ''
                }
            >
                {processing && <Spinner />}
                Ver mi queja
            </Button>
            <p className="text-sm text-muted-foreground">
                Si perdió la clave no podemos recuperarla, porque no sabemos
                quién es. Puede presentar la queja de nuevo y mencionar la
                referencia anterior.
            </p>
        </form>
    );
}
