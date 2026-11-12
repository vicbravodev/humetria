import ColumnChart from '@/components/humetria/column-chart';
import { niceCeiling } from '@/lib/chart';
import { yearColumns } from '@/lib/leave/compare';
import { previewDays } from '@/lib/leave/policy';
import type { PolicyRowDraft } from '@/lib/leave/policy';

/**
 * «Así quedan los días»: the days the policy gives by years of service as the rows stand now, against the law as a mark on
 * each column, before anything is saved. It follows what is typed.
 */
export default function PolicyPreview({
    rows,
    floors,
}: {
    rows: PolicyRowDraft[];
    floors: number[];
}) {
    const byYear = previewDays(rows, floors);
    const law = floors.slice(0, byYear.length);
    const top = niceCeiling(Math.max(...byYear, ...law), 5);

    return (
        <aside
            aria-label="Vista previa de los días"
            className="flex flex-col gap-4 card p-5 lg:sticky lg:top-6"
        >
            <h2 className="text-lg leading-[26px] font-semibold">
                Así quedan los días
            </h2>
            <ColumnChart
                label="Días por antigüedad como queda la política"
                columns={yearColumns(byYear, law, top)}
            />
            <p className="text-caption text-muted-foreground">
                La marca de cada columna es lo que da la ley ese año. Los
                periodos ya otorgados conservan los términos con que se
                otorgaron.
            </p>
        </aside>
    );
}
