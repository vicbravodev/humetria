import { Badge } from '@/components/ui/badge';
import { plural } from '@/lib/plural';
import type { CoverageRow } from '@/types';

/**
 * For each kind of person a complaint may be against: how many handlers would receive it. A zero is the only thing that
 * matters: the form refuses that option, because the complaint would reach the person it is about.
 */
export default function CoverageList({ rows }: { rows: CoverageRow[] }) {
    return (
        <ul className="divide-y card">
            {rows.map((row) => (
                <li
                    key={row.kind}
                    className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                >
                    <span className="font-medium">{row.label}</span>
                    <span className="flex items-center gap-3">
                        <span className="text-sm text-muted-foreground">
                            {row.count}{' '}
                            {plural(row.count, 'persona', 'personas')}
                        </span>
                        <Badge variant={row.count > 0 ? 'success' : 'warning'}>
                            {row.count > 0 ? 'Cubierto' : 'Falta'}
                        </Badge>
                    </span>
                </li>
            ))}
        </ul>
    );
}
