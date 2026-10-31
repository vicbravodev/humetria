import type { WorkCenterRow } from '@/types';

const CHIP =
    'inline-flex h-[26px] items-center gap-1.5 rounded-full bg-secondary px-2.5 text-[13px] leading-[18px] font-medium whitespace-nowrap';

/** The largest areas of a work center with their people, and how many more it has. Nothing while nobody works there. */
export default function WorkCenterAreas({
    workCenter,
}: {
    workCenter: WorkCenterRow;
}) {
    if (workCenter.areas.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-2">
            <h3 className="text-[13px] leading-[18px] text-muted-foreground">
                Áreas
            </h3>
            <ul className="flex flex-wrap gap-1.5">
                {workCenter.areas.map((area) => (
                    <li key={area.name} className={CHIP}>
                        {area.name}
                        <span className="font-normal text-muted-foreground tabular-nums">
                            {area.employees}
                        </span>
                    </li>
                ))}
                {workCenter.more_areas > 0 && (
                    <li className={`${CHIP} font-normal text-muted-foreground`}>
                        {workCenter.more_areas === 1
                            ? '1 área más'
                            : `${workCenter.more_areas} áreas más`}
                    </li>
                )}
            </ul>
        </div>
    );
}
