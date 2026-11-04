import { Globe } from 'lucide-react';

/**
 * The sheet that is printed and hung (one page): who informs, what the page has, the address in large text and its QR. On
 * paper it is always the light theme and shows nothing of the screen's controls.
 */
export default function PosterSheet({
    company,
    url,
    qr,
}: {
    company: string;
    url: string;
    qr: string;
}) {
    return (
        <article className="mx-auto flex w-full max-w-[720px] flex-col items-center gap-6 rounded-2xl border bg-card p-8 text-center text-card-foreground print:max-w-none print:rounded-none print:border-0">
            <p className="text-xl font-semibold">{company}</p>
            <h1 className="text-[40px] leading-[48px] font-semibold tracking-[-0.01em] text-balance">
                Información para las personas trabajadoras
            </h1>
            <p className="max-w-[560px] text-2xl leading-8 text-pretty">
                Consulte aquí la política de prevención, las medidas, cómo
                presentar una queja y los resultados.
            </p>
            <img
                src={qr}
                alt={`Código QR de la página de información de ${company}`}
                className="size-[320px] max-w-full"
            />
            <p className="flex items-start gap-2 text-xl font-semibold break-all">
                <Globe aria-hidden className="mt-1 size-5 shrink-0" />
                {url}
            </p>
            <p className="text-base">
                Escanee el código con su celular o escriba la dirección en su
                navegador.
            </p>
        </article>
    );
}
