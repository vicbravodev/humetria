import { Check } from 'lucide-react';
import Initials from '@/components/humetria/initials';
import PosterQr from '@/components/humetria/poster-qr';

const KINDS = [
    'Mal ambiente de trabajo',
    'Violencia laboral',
    'Un hecho muy grave que le afectó',
];

/**
 * The letter sheet of the channel that is printed and hung (board V4-Carteles; decisiones A16 y D6: print CSS, never a PDF):
 * whose it is, what it is for, the QR with the address to type, and what can be told there. On paper it is always light.
 */
export default function PosterSheet({
    company,
    qr,
    shortUrl,
    resolveWithinDays,
}: {
    company: string;
    qr: string;
    shortUrl: string;
    resolveWithinDays: number;
}) {
    return (
        <>
            <header className="flex min-w-0 items-center gap-3 font-semibold">
                <Initials
                    name={company}
                    kind="company"
                    className="size-8 text-caption"
                />
                <span className="truncate">{company}</span>
            </header>
            <div className="flex flex-col gap-4">
                <p className="text-caption font-semibold tracking-[0.06em] text-primary uppercase">
                    Quejas y denuncias
                </p>
                <h1 className="text-[40px] leading-[46px] font-semibold tracking-[-0.015em] text-balance">
                    ¿Algo no está bien en su trabajo?
                </h1>
                <p className="text-xl leading-8 text-muted-foreground">
                    Cuéntelo de forma anónima o confidencial. Le respondemos en{' '}
                    {resolveWithinDays} días.
                </p>
            </div>
            <PosterQr
                qr={qr}
                alt={`Código QR del canal de quejas de ${company}`}
                url={shortUrl}
            />
            <ul className="flex flex-col gap-3">
                {KINDS.map((kind) => (
                    <li key={kind} className="flex items-center gap-3 text-lg">
                        <span
                            aria-hidden
                            className="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground"
                        >
                            <Check className="size-3.5" strokeWidth={3} />
                        </span>
                        {kind}
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
