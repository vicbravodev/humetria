import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { dossierStanding } from '@/lib/nom035/dossier';
import type { Nom035DossierRowData } from '@/types';

/** One requirement of the dossier as a section: its numeral, state, what is behind it and where its evidence is printed. */
export default function Nom035DossierRow({
    row,
}: {
    row: Nom035DossierRowData;
}) {
    const standing = dossierStanding(row.state);

    return (
        <section
            aria-label={`Numeral ${row.numeral}`}
            className="flex break-inside-avoid flex-col gap-2 border-t pt-5"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <h2 className="text-lg leading-6 font-semibold">
                    <span className="text-muted-foreground tabular-nums">
                        {row.numeral}
                    </span>{' '}
                    {row.title}
                </h2>
                <Badge variant={standing.variant}>{standing.label}</Badge>
            </div>
            {row.detail !== null && (
                <p className="text-sm text-muted-foreground">{row.detail}</p>
            )}
            {row.evidence !== null && (
                <p className="text-sm">
                    Evidencia:{' '}
                    <Link
                        href={row.evidence.href}
                        className="font-medium text-primary underline underline-offset-4"
                    >
                        {row.evidence.label}
                    </Link>
                </p>
            )}
        </section>
    );
}
