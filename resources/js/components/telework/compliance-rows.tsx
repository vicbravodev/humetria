import ComplianceRow from '@/components/telework/compliance-row';
import type { ComplianceRow as Row } from '@/types';

/** The 17 rows of the inspection table 10.3, in the norm's order (criterio C-30). */
export default function ComplianceRows({ rows }: { rows: Row[] }) {
    return (
        <section className="grid gap-3" aria-labelledby="rows-title">
            <h2 id="rows-title" className="text-xl font-semibold">
                Tabla de inspección (10.3)
            </h2>
            <ol
                aria-label="Renglones de la tabla 10.3"
                className="overflow-hidden rounded-xl border bg-card"
            >
                {rows.map((row) => (
                    <ComplianceRow key={row.numeral} row={row} />
                ))}
            </ol>
        </section>
    );
}
