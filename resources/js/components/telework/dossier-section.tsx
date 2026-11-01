import DossierEvidenceBlock from '@/components/telework/dossier-evidence';
import { Badge } from '@/components/ui/badge';
import { rowTone } from '@/lib/telework/center';
import type { DossierData, DossierRow } from '@/types';

/** One row of table 10.3 as a section of the dossier: the numeral, its state and detail, and the evidence. It never splits across pages. */
export default function DossierSection({
    row,
    data,
}: {
    row: DossierRow;
    data: DossierData;
}) {
    return (
        <section
            aria-label={`Numeral ${row.numeral}`}
            className="flex break-inside-avoid flex-col gap-3 border-t pt-5"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-0.5">
                    <h2 className="text-lg leading-6 font-semibold">
                        <span className="text-muted-foreground tabular-nums">
                            {row.numeral}
                        </span>{' '}
                        {row.label}
                    </h2>
                    {row.detail !== null && (
                        <p className="text-[15px] leading-[22px] text-muted-foreground">
                            {row.detail}
                        </p>
                    )}
                </div>
                <Badge variant={rowTone(row.state)}>{row.stateLabel}</Badge>
            </div>
            <DossierEvidenceBlock row={row} data={data} />
        </section>
    );
}
