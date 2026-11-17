import { CircleCheck } from 'lucide-react';

/** What stays once the person confirmed: the day they did (criterio C-53). */
export default function PortalConfirmed({ on }: { on: string | null }) {
    return (
        <p className="flex items-center gap-2 rounded-xl bg-accent px-4 py-3 text-base text-accent-foreground">
            <CircleCheck aria-hidden className="size-5 shrink-0" />
            Usted confirmó el {on} que leyó esta política.
        </p>
    );
}
