import type { LucideIcon } from 'lucide-react';

/** An icon on its soft square (pantallas: Panel, Centros): what a row or a card is about, before its title. */
export default function IconTile({ icon: Icon }: { icon: LucideIcon }) {
    return (
        <span
            aria-hidden
            className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
        >
            <Icon className="size-5" />
        </span>
    );
}
