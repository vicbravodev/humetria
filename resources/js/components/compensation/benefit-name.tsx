import { Badge } from '@/components/ui/badge';
import type { BenefitRow } from '@/types';

/** A benefit's cell: its name, whether it is of the base catalog, and how it is worked out. */
export default function BenefitName({ benefit }: { benefit: BenefitRow }) {
    return (
        <div className="min-w-44">
            <p className="flex flex-wrap items-center gap-2 font-medium">
                {benefit.name}
                {benefit.is_base && (
                    <Badge variant="secondary" className="h-5 px-2 text-xs">
                        Base
                    </Badge>
                )}
            </p>
            <p className="text-caption text-muted-foreground">
                {benefit.kind_label}
            </p>
        </div>
    );
}
