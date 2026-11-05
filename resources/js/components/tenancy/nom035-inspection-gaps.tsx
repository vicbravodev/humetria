import { inspectionGaps } from '@/lib/nom035/route';
import type { RouteRow } from '@/types';

/** «Te faltaría» (board V2-Ruta-N035): what an inspection would find missing, by name. */
export default function Nom035InspectionGaps({ rows }: { rows: RouteRow[] }) {
    const missing = inspectionGaps(rows);

    if (missing.length === 0) {
        return null;
    }

    return (
        <ul aria-label="Te faltaría" className="flex flex-col gap-2.5">
            {missing.map((title) => (
                <li
                    key={title}
                    className="grid grid-cols-[20px_minmax(0,1fr)] items-start gap-2.5"
                >
                    <span
                        aria-hidden
                        className="mt-0.5 size-4 rounded-full border-[1.5px] border-dashed border-risk-alto"
                    />
                    <span>{title}</span>
                </li>
            ))}
        </ul>
    );
}
