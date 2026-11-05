import Initials from '@/components/humetria/initials';
import type { CoverageByKind } from '@/types';

/** Who receives it: the first handler with their role, and how many more. */
export default function CoverageReceivers({ row }: { row: CoverageByKind }) {
    const [first, ...others] = row.handlers;

    if (first === undefined) {
        return (
            <span className="flex items-start gap-3">
                <span
                    aria-hidden
                    className="size-8 shrink-0 rounded-full border border-dashed border-risk-alto"
                />
                <span className="flex flex-col text-sm">
                    <span className="font-medium text-risk-alto-fg">
                        Nadie todavía
                    </span>
                    <span className="text-muted-foreground">
                        El formulario no acepta esta opción
                    </span>
                </span>
            </span>
        );
    }

    return (
        <span className="flex items-center gap-3">
            <Initials name={first.name} />
            <span className="flex flex-col text-sm">
                <span className="font-medium">
                    {first.name}
                    {others.length > 0 && ` y ${others.length} más`}
                </span>
                <span className="text-muted-foreground">{first.roleLabel}</span>
            </span>
        </span>
    );
}
