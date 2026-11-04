import { Lock } from 'lucide-react';

/**
 * The sheet that is printed and hung (one page): who asks, the QR, the address to type if the code cannot be read, and what
 * it promises: anonymous or confidential, with its limits said elsewhere, on the page itself.
 */
export default function PosterSheet({
    company,
    qr,
    shortUrl,
    procedure,
}: {
    company: string;
    qr: string;
    shortUrl: string;
    procedure: string;
}) {
    return (
        <article className="mx-auto flex w-full max-w-[720px] flex-col items-center gap-6 rounded-2xl border bg-card p-8 text-center text-card-foreground print:max-w-none print:rounded-none print:border-0">
            <p className="text-xl font-semibold">{company}</p>
            <h1 className="text-[40px] leading-[48px] font-semibold tracking-[-0.01em] text-balance">
                ¿Algo no está bien en tu trabajo?
            </h1>
            <p className="max-w-[520px] text-2xl leading-8 text-pretty">
                Cuéntalo aquí, de forma anónima o confidencial. Escanea el
                código con tu celular.
            </p>
            <img
                src={qr}
                alt={`Código QR del canal de quejas de ${company}`}
                className="size-[320px] max-w-full"
            />
            <p className="flex items-center gap-2 text-xl font-semibold break-all">
                <Lock aria-hidden className="size-5 shrink-0" />
                {shortUrl}
            </p>
            <p className="max-w-[560px] text-lg leading-7 whitespace-pre-line">
                {procedure}
            </p>
            <p className="text-base">
                Quejas por un mal ambiente de trabajo, denuncias de violencia
                laboral y avisos de un hecho que te marcó.
            </p>
        </article>
    );
}
