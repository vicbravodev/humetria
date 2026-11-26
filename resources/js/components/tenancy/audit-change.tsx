import type { AuditChange as Change } from '@/types';

/**
 * What an entry changed (tablero Bitácora): each field as it was, after a −, and as it became, after a +; the words
 * «Antes» and «Después» are said to screen readers, and the sign carries it besides the color.
 */
export default function AuditChange({ change }: { change: Change[] }) {
    return (
        <section aria-labelledby="audit-change" className="grid gap-2">
            <h3
                id="audit-change"
                className="text-caption font-medium tracking-wide text-muted-foreground uppercase"
            >
                Cambio
            </h3>
            <ul className="grid gap-2 rounded-lg bg-muted px-3.5 py-3 font-mono text-caption">
                {change.map((row) => (
                    <li key={row.field} className="grid break-words">
                        <span className="text-risk-alto-fg">
                            <span aria-hidden>− </span>
                            <span className="sr-only">Antes, </span>
                            {row.field}: {row.before}
                        </span>
                        <span className="text-risk-bajo-fg">
                            <span aria-hidden>+ </span>
                            <span className="sr-only">Después, </span>
                            {row.field}: {row.after}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
