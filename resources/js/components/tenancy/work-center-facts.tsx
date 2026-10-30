import { guidesShort } from '@/lib/work-centers';
import type { WorkCenterRow } from '@/types';

/** The three facts of a work center card: its people, the guides that apply by that number and its next evaluation. */
export default function WorkCenterFacts({
    workCenter,
    next,
}: {
    workCenter: WorkCenterRow;
    next: string;
}) {
    return (
        <dl className="grid grid-cols-3 gap-3 border-y py-3.5">
            <div className="flex flex-col">
                <dt className="text-[13px] leading-[18px] text-muted-foreground">
                    Personal
                </dt>
                <dd className="text-[22px] leading-[30px] font-semibold tabular-nums">
                    {workCenter.employees}
                </dd>
            </div>
            <div className="flex flex-col">
                <dt className="text-[13px] leading-[18px] text-muted-foreground">
                    Guías NOM-035
                </dt>
                <dd className="text-[22px] leading-[30px] font-semibold">
                    {guidesShort(workCenter.guides)}
                </dd>
            </div>
            <div className="flex flex-col">
                <dt className="text-[13px] leading-[18px] text-muted-foreground">
                    Siguiente evaluación
                </dt>
                <dd className="pt-1 text-sm font-medium">{next}</dd>
            </div>
        </dl>
    );
}
