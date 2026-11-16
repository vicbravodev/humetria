import { useId, useState } from 'react';
import type { FormEvent } from 'react';
import StatusLine from '@/components/worker-access/status-line';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { useRecentProof } from '@/hooks/use-recent-proof';
import { codeMessage, codeSentMessage } from '@/lib/worker-access/proof';

/** For a person without a key: a code of six numbers by email, written here. Asking again voids the code before. */
export default function ProofCodeForm({
    minutes,
    proof,
}: {
    minutes: number;
    proof: ReturnType<typeof useRecentProof>;
}) {
    const id = useId();
    const [code, setCode] = useState('');
    const submit = (event: FormEvent) => {
        event.preventDefault();
        void proof.checkCode(code);
    };
    const failure = proof.codeFailure !== null && (
        <StatusLine text={codeMessage(proof.codeFailure)} problem />
    );

    if (!proof.sent) {
        return (
            <div className="flex flex-col gap-3">
                <p className="text-base text-muted-foreground">
                    Le enviaremos un código de seis números a su correo.
                </p>
                <Button
                    className="h-14 w-full text-lg font-semibold"
                    disabled={proof.working}
                    onClick={() => void proof.askCode()}
                >
                    {proof.working && <Spinner className="size-6" />}
                    Enviarme un código
                </Button>
                {failure}
            </div>
        );
    }

    return (
        <form onSubmit={submit} className="flex flex-col gap-3">
            <StatusLine text={codeSentMessage(minutes)} />
            <Label htmlFor={id}>Código</Label>
            <Input
                id={id}
                inputMode="numeric"
                autoComplete="one-time-code"
                pattern="[0-9]{6}"
                maxLength={6}
                required
                value={code}
                onChange={(event) => setCode(event.target.value.trim())}
                className="h-14 text-center text-2xl tracking-[0.3em]"
            />
            <Button
                type="submit"
                className="h-14 w-full text-lg font-semibold"
                disabled={proof.working}
            >
                {proof.working && <Spinner className="size-6" />}
                Confirmar código
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="lg"
                disabled={proof.working}
                onClick={() => void proof.askCode()}
            >
                Enviarme otro código
            </Button>
            {failure}
        </form>
    );
}
