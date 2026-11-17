import { Check } from 'lucide-react';
import Initials from '@/components/humetria/initials';
import PosterQr from '@/components/humetria/poster-qr';

/**
 * The letter sheet that is printed and hung (board V4-Carteles; decisiones A16 y D6: print CSS, never a PDF): who informs and
 * for which center, what the page has, its QR with the address and what it promises, all of it true. On paper it is always
 * the light theme and shows nothing of the screen's controls.
 */
export default function PosterSheet({
    company,
    center,
    url,
    qr,
    channel,
}: {
    company: string;
    center: string | null;
    url: string;
    qr: string;
    channel: boolean;
}) {
    const promises = [
        'No pide cuenta ni contraseña',
        'No guardamos quién entra',
        ...(channel ? ['También ahí puede presentar una queja'] : []),
    ];

    return (
        <>
            <header className="flex items-center justify-between gap-3">
                <span className="flex min-w-0 items-center gap-3 font-semibold">
                    <Initials name={company} className="size-8 text-caption" />
                    <span className="truncate">{company}</span>
                </span>
                {center !== null && (
                    <span className="text-sm text-muted-foreground">
                        {center}
                    </span>
                )}
            </header>
            <div className="flex flex-col gap-4">
                <p className="text-caption font-semibold tracking-[0.06em] text-primary uppercase">
                    Para las personas trabajadoras
                </p>
                <h1 className="text-[40px] leading-[46px] font-semibold tracking-[-0.015em] text-balance">
                    Lo que la empresa le informa sobre su bienestar
                </h1>
                <p className="text-xl leading-8 text-muted-foreground">
                    La política, las medidas y los resultados de la evaluación,
                    en su celular.
                </p>
            </div>
            <PosterQr
                qr={qr}
                alt={`Código QR de la página de ${center ?? company}`}
                url={url}
            />
            <ul className="flex flex-col gap-3">
                {promises.map((promise) => (
                    <li
                        key={promise}
                        className="flex items-center gap-3 text-lg"
                    >
                        <span
                            aria-hidden
                            className="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground"
                        >
                            <Check className="size-3.5" strokeWidth={3} />
                        </span>
                        {promise}
                    </li>
                ))}
            </ul>
            <p className="text-center text-caption text-muted-foreground">
                hecho con{' '}
                <span className="font-semibold text-foreground">humetría</span>
            </p>
        </>
    );
}
