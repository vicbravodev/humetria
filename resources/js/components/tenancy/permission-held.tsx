import { Check, Minus } from 'lucide-react';

/** Whether a role holds a permission: a check and «Sí», or a dash and «No»; never color alone. */
export default function PermissionHeld({ held }: { held: boolean }) {
    return held ? (
        <span className="inline-flex items-center gap-1.5">
            <Check aria-hidden className="size-4 text-primary" />
            Sí
        </span>
    ) : (
        <span className="inline-flex items-center gap-1.5 text-muted-foreground">
            <Minus aria-hidden className="size-4" />
            No
        </span>
    );
}
