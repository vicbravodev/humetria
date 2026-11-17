import { Check, Copy, KeyRound } from 'lucide-react';
import CodeRow from '@/components/complaints-public/code-row';
import PublicShell from '@/components/complaints-public/public-shell';
import ShareReceipt from '@/components/complaints-public/share-receipt';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { copyText, keyNote } from '@/lib/complaints/receipt';
import type { Receipt } from '@/lib/complaints/receipt';
import { show } from '@/routes/complaints/follow-up';

/**
 * «Recibimos su queja» (board V4-Quejas-Form, decisión A9): shown once, the reference and the key the system generated to
 * follow it up, when it will be acknowledged, and that without them nobody can answer, because nobody knows who wrote.
 */
export default function ReceiptCard({
    company,
    receipt,
    code,
    acknowledgeBy,
    named,
}: {
    company: string;
    receipt: Receipt;
    code: string;
    /** «5 de noviembre de 2026»: the last day the company set to acknowledge it. */
    acknowledgeBy: string;
    /** Whether the person gave their name: the note about the key does not say «no sabemos quién es» to them. */
    named: boolean;
}) {
    const [copied, copy] = useClipboard();
    const text = copyText(receipt.reference, receipt.key);

    return (
        <PublicShell
            company={company}
            actions={
                <>
                    <Button
                        type="button"
                        size="lg"
                        className="w-full"
                        onClick={() => void copy(text)}
                    >
                        {copied === text ? (
                            <Check aria-hidden />
                        ) : (
                            <Copy aria-hidden />
                        )}
                        {copied === text
                            ? 'Copiado'
                            : 'Copiar referencia y clave'}
                    </Button>
                    <Button size="lg" variant="link" className="w-full" asChild>
                        <a href={show.url({ code })}>Ver cómo va</a>
                    </Button>
                </>
            }
        >
            <section
                className="flex w-full max-w-xl flex-col gap-5"
                aria-live="polite"
            >
                <div className="flex flex-col items-center gap-2.5 pt-3 text-center">
                    <span
                        aria-hidden
                        className="inline-flex size-[52px] items-center justify-center rounded-full bg-accent text-primary"
                    >
                        <Check className="size-6" strokeWidth={2.5} />
                    </span>
                    <h1 className="text-2xl leading-[30px] font-semibold">
                        Recibimos su queja
                    </h1>
                    <p className="text-muted-foreground">
                        Le confirmaremos que alguien la leyó a más tardar el{' '}
                        {acknowledgeBy}.
                    </p>
                </div>
                <div className="flex flex-col gap-4 card p-5">
                    <CodeRow label="Su referencia" code={receipt.reference} />
                    <CodeRow label="Su clave" code={receipt.key} />
                </div>
                <p className="flex items-start gap-3 rounded-lg bg-warning px-4 py-3 text-base text-warning-foreground">
                    <KeyRound aria-hidden className="mt-1 size-4 shrink-0" />
                    <span>
                        Guárdela ahora: no se vuelve a mostrar. Si el
                        dispositivo no es suyo, cierre esta pantalla al
                        terminar. {keyNote(named)}
                    </span>
                </p>
                <ShareReceipt receipt={receipt} />
            </section>
        </PublicShell>
    );
}
