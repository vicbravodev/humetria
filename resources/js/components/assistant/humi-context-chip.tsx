import { Eye, X } from 'lucide-react';

/** What Humi is looking at, beside the box: the page open goes with each question until the person removes it. */
export default function HumiContextChip({
    label,
    onRemove,
}: {
    label: string;
    onRemove: () => void;
}) {
    return (
        <span className="inline-flex h-8 max-w-full items-center gap-2 self-start rounded-full bg-muted pr-1.5 pl-2.5 text-caption font-medium text-muted-foreground">
            <Eye aria-hidden className="size-4 shrink-0" />
            <span className="truncate">Viendo: {label}</span>
            <button
                type="button"
                aria-label="Quitar contexto"
                onClick={onRemove}
                className="relative flex size-6 shrink-0 items-center justify-center rounded-full after:absolute after:-inset-2.5 hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
            >
                <X aria-hidden className="size-3" />
            </button>
        </span>
    );
}
