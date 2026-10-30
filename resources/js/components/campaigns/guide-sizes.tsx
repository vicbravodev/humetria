import { Building2, Info } from 'lucide-react';
import { centerSize, SIZES } from '@/lib/evaluation-centers';
import type { CentersBySize, WorkCenterEvaluation } from '@/types';

/**
 * Which guide applies by the size of the work center (num. 2), with the centers of this page in each size and how many
 * more there are in the rest.
 */
export default function GuideSizes({
    centers,
    counts,
}: {
    /** The work centers on this page. */
    centers: WorkCenterEvaluation[];
    /** Every work center in use, counted by size. */
    counts: CentersBySize;
}) {
    return (
        <section
            aria-labelledby="guide-sizes"
            className="overflow-hidden rounded-xl border bg-card"
        >
            <div className="flex items-center gap-3 border-b px-5 py-[18px]">
                <Info
                    aria-hidden
                    className="size-5 shrink-0 text-muted-foreground"
                />
                <h2 id="guide-sizes" className="grow text-sm font-medium">
                    Qué guía aplica según el tamaño del centro
                </h2>
                <span className="text-[13px] leading-[18px] text-muted-foreground max-sm:hidden">
                    Numeral 2 de la NOM-035
                </span>
            </div>
            <div className="grid divide-border max-md:divide-y md:grid-cols-3 md:divide-x">
                {SIZES.map((size) => {
                    const here = centers.filter(
                        (center) => centerSize(center.guides)?.key === size.key,
                    );
                    const more = counts[size.key] - here.length;

                    return (
                        <div
                            key={size.key}
                            className="flex flex-col gap-2 px-5 py-[18px]"
                        >
                            <span className="text-sm text-muted-foreground">
                                {size.people}
                            </span>
                            <span className="text-lg leading-[26px] font-semibold">
                                {size.guides}
                            </span>
                            {counts[size.key] === 0 && (
                                <span className="text-[13px] leading-[18px] text-muted-foreground">
                                    Ningún centro
                                </span>
                            )}
                            <ul className="flex flex-wrap gap-1.5 empty:hidden">
                                {here.map((center) => (
                                    <li
                                        key={center.id}
                                        className="inline-flex h-[26px] items-center gap-1.5 rounded-full bg-accent px-2.5 text-[13px] leading-[18px] font-medium text-primary"
                                    >
                                        <Building2
                                            aria-hidden
                                            className="size-3.5"
                                        />
                                        {center.name}
                                    </li>
                                ))}
                                {more > 0 && (
                                    <li className="inline-flex h-[26px] items-center text-[13px] text-muted-foreground">
                                        y {more} más
                                    </li>
                                )}
                            </ul>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
