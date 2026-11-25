import type { AuditChange as Change } from '@/types';

/** What an entry changed, field by field: what it was and what it became, each said in words and not by color alone. */
export default function AuditChange({ change }: { change: Change[] }) {
    return (
        <section aria-labelledby="audit-change" className="grid gap-2">
            <h3
                id="audit-change"
                className="text-caption font-medium tracking-wide text-muted-foreground uppercase"
            >
                Cambio
            </h3>
            <ul className="grid gap-3 rounded-lg bg-muted px-4 py-3 text-sm">
                {change.map((row) => (
                    <li key={row.field} className="grid gap-0.5">
                        <span className="font-medium">{row.field}</span>
                        <span className="break-words text-muted-foreground">
                            Antes: {row.before}
                        </span>
                        <span className="break-words">
                            Después: {row.after}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
