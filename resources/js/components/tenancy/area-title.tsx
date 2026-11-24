import { Lock } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { phraseOf } from '@/lib/permission-areas';
import type { PermissionArea } from '@/lib/permission-areas';
import type { PermissionGroup } from '@/types';

/** The name of an area with its tags, and under it what its level lets do, why it is locked, or that its role holds a mix. */
export default function AreaTitle({
    area,
    held,
    groups,
    reason,
    tags,
}: {
    area: PermissionArea;
    held: string[];
    groups: PermissionGroup[];
    reason: string | null;
    tags: { additional: boolean; sensitive: boolean; mixed: boolean };
}) {
    const line = tags.mixed
        ? 'El rol tiene una combinación propia que no se cambia aquí. Para otro nivel, cámbiale el rol.'
        : phraseOf(area, held, groups);

    return (
        <div className="flex min-w-0 flex-col gap-0.5">
            <span className="flex flex-wrap items-center gap-2 font-medium">
                {area.label}
                {tags.additional && <Badge>Adicional</Badge>}
                {tags.mixed && (
                    <Badge variant="secondary">Combinación del rol</Badge>
                )}
                {tags.sensitive && <Badge variant="warning">Sensible</Badge>}
            </span>
            <span className="text-sm text-pretty text-muted-foreground">
                {reason === null ? (
                    line
                ) : (
                    <span className="flex items-center gap-1.5">
                        <Lock aria-hidden className="size-3.5 shrink-0" />
                        {reason}
                    </span>
                )}
            </span>
        </div>
    );
}
