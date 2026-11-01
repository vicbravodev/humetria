import { useForm } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { describeDate } from '@/lib/calendar-date';
import { acknowledge, file } from '@/routes/portal/documents';
import type { PortalDocument } from '@/types';

/** A document the company published: the person reads the PDF and then confirms, once, that they did. */
export default function PortalDocumentCard({
    document,
    token,
}: {
    document: PortalDocument;
    token: string;
}) {
    const form = useForm({});

    return (
        <li className="flex items-start gap-3.5 rounded-xl border bg-card p-4">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                <FileText aria-hidden className="size-5" />
            </span>
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <p className="text-base font-semibold">{document.title}</p>
                <p className="text-base text-muted-foreground">
                    Publicado el{' '}
                    {describeDate(document.publishedOn) ?? document.publishedOn}
                </p>
                <p className="mt-1 text-base">
                    Léalo y confirme que lo leyó. Queda constancia de la fecha,
                    la hora y la dirección IP desde la que lo confirma.
                </p>
                <div className="mt-2 flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        className="h-12 text-base"
                        asChild
                    >
                        <a
                            href={file.url({ token, document: document.id })}
                            target="_blank"
                            rel="noreferrer"
                        >
                            Ver el documento
                        </a>
                    </Button>
                    <Button
                        className="h-12 text-base"
                        disabled={form.processing}
                        onClick={() =>
                            form.post(
                                acknowledge.url({
                                    token,
                                    document: document.id,
                                }),
                            )
                        }
                    >
                        Confirmo que lo leí
                    </Button>
                </div>
            </div>
        </li>
    );
}
