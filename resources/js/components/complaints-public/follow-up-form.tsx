import { useState } from 'react';
import type { ReactNode } from 'react';
import PublicShell from '@/components/complaints-public/public-shell';
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
    company,
    notice,
    error,
    processing,
    onOpen,
}: {
    company: string;
    /** What goes above the form: the channel is paused. */
    notice: ReactNode;
    error: string | undefined;
    processing: boolean;
    onOpen: (reference: string, key: string) => void;
}) {
    const [reference, setReference] = useState('');
    const [key, setKey] = useState('');

    const ready = reference.trim() !== '' && key.trim() !== '';

    return (
        <PublicShell
            company={company}
            actions={
                <Button
                    size="lg"
                    className="w-full"
                    form="follow-up-form"
                    disabled={processing || !ready}
                >
                    {processing && <Spinner />}
                    Ver mi queja
                </Button>
            }
        >
            <div className="flex w-full max-w-xl flex-col gap-6">
                {notice}
                <form
                    id="follow-up-form"
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
                            onChange={(event) =>
                                setReference(event.target.value)
                            }
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
                    <p className="text-sm text-muted-foreground">
                        Si perdió la clave no podemos recuperarla. Puede
                        presentar la queja de nuevo y mencionar la referencia
                        anterior.
                    </p>
                </form>
            </div>
        </PublicShell>
    );
}
