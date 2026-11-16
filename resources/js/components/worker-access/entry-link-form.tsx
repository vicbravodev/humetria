import { useId, useState } from 'react';
import type { FormEvent } from 'react';
import StatusLine from '@/components/worker-access/status-line';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { LinkRequestOutcome } from '@/lib/worker-access/entry';
import { linkRequestMessage } from '@/lib/worker-access/entry';

/** For a device without the person's key: they write their email and get a one-time link. The answer is the same for anyone. */
export default function EntryLinkForm({
    working,
    outcome,
    onAsk,
}: {
    working: boolean;
    outcome: LinkRequestOutcome | null;
    onAsk: (email: string) => void;
}) {
    const id = useId();
    const [email, setEmail] = useState('');
    const submit = (event: FormEvent) => {
        event.preventDefault();
        onAsk(email);
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-3 card p-5">
            <p className="text-xl leading-7 font-semibold">
                Entrar con un enlace por correo
            </p>
            <p className="text-base text-muted-foreground">
                Sirve en una computadora o un teléfono sin su llave.
            </p>
            <Label htmlFor={id}>Su correo</Label>
            <Input
                id={id}
                type="email"
                autoComplete="email"
                inputMode="email"
                required
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                className="h-12 text-lg"
            />
            <Button
                type="submit"
                variant="outline"
                size="lg"
                disabled={working}
            >
                Enviarme un enlace
            </Button>
            {outcome !== null && (
                <StatusLine
                    text={linkRequestMessage(outcome)}
                    problem={outcome !== 'sent'}
                />
            )}
        </form>
    );
}
