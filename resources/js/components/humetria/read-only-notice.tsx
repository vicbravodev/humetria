import { Eye } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

/**
 * A screen a role may see but not change (board A8, «Rol de solo lectura»): the reason on the page, so a missing button is
 * never a mystery. It goes under the header of every screen whose actions the viewer does not get.
 */
export default function ReadOnlyNotice() {
    return (
        <div
            role="status"
            data-slot="read-only-notice"
            className="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl bg-muted px-5 py-3.5"
        >
            <Eye
                aria-hidden
                className="size-5 shrink-0 text-muted-foreground"
            />
            <p className="min-w-0 flex-1 text-meta">
                Tu rol puede ver esta pantalla, pero no modificar nada. Pide al
                dueño o a RH que te den permiso de edición.
            </p>
            <Badge variant="secondary">Solo lectura</Badge>
        </div>
    );
}
