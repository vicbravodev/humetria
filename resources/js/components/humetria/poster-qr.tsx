import { Fragment } from 'react';

/**
 * The QR of a poster with the address to type if it fails. The address breaks only after a slash, never in the middle of a
 * part (not even at the hyphens of a token), so it can be read and typed as it is printed; the QR carries the whole link.
 */
export default function PosterQr({
    qr,
    alt,
    url,
}: {
    qr: string;
    alt: string;
    url: string;
}) {
    const parts = url.replace(/^https?:\/\//u, '').split('/');

    return (
        <div className="flex flex-wrap items-center gap-6">
            <img
                src={qr}
                alt={alt}
                className="size-[200px] rounded-xl border-2 border-foreground p-2"
            />
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <p className="font-semibold">Escanee con su celular</p>
                <p data-slot="poster-address" className="font-mono text-sm">
                    {parts.map((part, index) => (
                        <Fragment key={index}>
                            {index > 0 && <wbr />}
                            <span className="whitespace-nowrap">
                                {index < parts.length - 1 ? `${part}/` : part}
                            </span>
                        </Fragment>
                    ))}
                </p>
            </div>
        </div>
    );
}
