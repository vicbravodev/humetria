import { Link } from '@inertiajs/react';
import { useSeatingHref } from '@/hooks/use-seating-href';
import { describeDate } from '@/lib/calendar-date';
import type { DossierLink, SeatingDossierDocument } from '@/types';

type Levels = { low: number; medium: number; high: number };

/**
 * What the dossier adds to the requirements: how many assessed people fall in each risk level (no names), the documents with
 * their incisos, what stays inside the application as links, and the note on the fine.
 */
export default function DossierExtras({
    workCenterId,
    levels,
    documents,
    links,
    fineNote,
}: {
    workCenterId: string;
    levels: Levels;
    documents: SeatingDossierDocument[];
    links: DossierLink[];
    fineNote: string;
}) {
    const hrefOf = useSeatingHref(workCenterId);

    return (
        <>
            <section className="flex break-inside-avoid flex-col gap-2 border-t pt-5">
                <h2 className="text-lg leading-6 font-semibold">
                    Niveles de riesgo de las personas evaluadas
                </h2>
                <p className="text-sm tabular-nums">
                    Bajo {levels.low} · Medio {levels.medium} · Alto{' '}
                    {levels.high}
                </p>
            </section>
            {documents.length > 0 && (
                <section className="flex break-inside-avoid flex-col gap-2 border-t pt-5">
                    <h2 className="text-lg leading-6 font-semibold">
                        Documentos
                    </h2>
                    <ul className="flex flex-col gap-1 text-sm">
                        {documents.map((document) => (
                            <li
                                key={`${document.title}-${document.documentOn}`}
                            >
                                {document.kindLabel}: {document.title}
                                {document.incisos.length > 0 &&
                                    ` (incisos ${document.incisos.join(', ')})`}
                                {' · '}
                                {describeDate(document.documentOn) ??
                                    document.documentOn}
                            </li>
                        ))}
                    </ul>
                </section>
            )}
            <section className="flex break-inside-avoid flex-col gap-2 border-t pt-5">
                <h2 className="text-lg leading-6 font-semibold">
                    Solo dentro de la aplicación
                </h2>
                <p className="text-sm text-muted-foreground">
                    Esto no se imprime porque el expediente puede salir del
                    sistema: se consulta en Humetría.
                </p>
                <ul className="flex flex-col gap-1 text-sm">
                    {links.map((link) => (
                        <li
                            key={link.label}
                            className="flex flex-wrap items-center gap-x-1.5"
                        >
                            {link.open ? (
                                <Link
                                    href={hrefOf(link.key)}
                                    className="touch-hit text-primary hover:underline"
                                >
                                    {link.label}
                                </Link>
                            ) : (
                                link.label
                            )}
                            <span className="text-muted-foreground tabular-nums">
                                ({link.count})
                            </span>
                        </li>
                    ))}
                </ul>
            </section>
            <p className="border-t pt-5 text-sm text-muted-foreground">
                {fineNote}
            </p>
        </>
    );
}
