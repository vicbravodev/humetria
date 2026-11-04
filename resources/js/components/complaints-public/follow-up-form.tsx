import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/humetria/input-error';

/** Asks for the reference and the key; they are kept only in this page's memory. */
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
            className="flex flex-col gap-4 card p-5"
            onSubmit={(event) => {
                event.preventDefault();
                onOpen(reference, key);
            }}
        >
            <p>
                Escriba la referencia y la clave que recibió al presentar su
                queja. No las guardamos en este dispositivo: las pedimos cada
                vez.
            </p>
            <div className="grid gap-2">
                <Label htmlFor="follow-up-reference">Referencia</Label>
                <Input
                    id="follow-up-reference"
                    value={reference}
                    autoComplete="off"
                    autoCapitalize="characters"
                    onChange={(event) => setReference(event.target.value)}
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="follow-up-key">Clave</Label>
                <Input
                    id="follow-up-key"
                    value={key}
                    autoComplete="off"
                    autoCapitalize="characters"
                    onChange={(event) => setKey(event.target.value)}
                />
            </div>
            <InputError message={error} />
            <Button
                className="self-start"
                disabled={
                    processing || reference.trim() === '' || key.trim() === ''
                }
            >
                {processing && <Spinner />}
                Abrir mi caso
            </Button>
        </form>
    );
}
