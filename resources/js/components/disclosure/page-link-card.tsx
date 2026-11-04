import { Check, Copy, ExternalLink, Link2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { expiryText, linkVariant } from '@/lib/page-link';
import type { PageLinkData } from '@/types';

/**
 * The permanent link of the page of the workers (pantalla Ajustes: a card with its value): its state, the address RH prints
 * and shares, to copy or to open as a person would, and its dates (criterio C-55).
 */
export default function PageLinkCard({ link }: { link: PageLinkData }) {
    const [copied, copy] = useClipboard();
    const url = link.url;

    return (
        <section
            aria-labelledby="page-link"
            className="flex flex-col gap-4 card p-5 md:p-6"
        >
            <div className="flex flex-wrap items-center gap-3">
                <Link2 aria-hidden className="size-5 text-primary" />
                <h2 id="page-link" className="text-lg font-semibold">
                    Enlace de la página
                </h2>
                <Badge variant={linkVariant(link.state.value)}>
                    {link.state.label}
                </Badge>
            </div>
            {url === null ? (
                <p role="alert" className="text-base text-destructive">
                    Este enlace ya no se puede mostrar porque cambió la llave de
                    la aplicación. Genera uno nuevo y cambia los carteles.
                </p>
            ) : (
                <div className="flex flex-wrap items-center gap-3">
                    <code className="min-w-0 flex-[1_1_260px] rounded-lg bg-muted px-3 py-2.5 text-sm break-all">
                        {url}
                    </code>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => void copy(url)}
                    >
                        {copied === url ? (
                            <Check aria-hidden />
                        ) : (
                            <Copy aria-hidden />
                        )}
                        {copied === url ? 'Copiado' : 'Copiar enlace'}
                    </Button>
                    <Button asChild variant="outline">
                        <a href={url} target="_blank" rel="noopener noreferrer">
                            <ExternalLink aria-hidden />
                            Ver como persona
                        </a>
                    </Button>
                </div>
            )}
            <ul className="flex flex-col gap-1 text-meta text-muted-foreground">
                <li>Creado el {link.createdOn}.</li>
                {link.rotatedOn !== null && (
                    <li>Se generó uno nuevo el {link.rotatedOn}.</li>
                )}
                <li>{expiryText(link)}</li>
            </ul>
        </section>
    );
}
