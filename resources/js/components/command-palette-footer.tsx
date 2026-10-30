import { ArrowDown, ArrowUp, CornerDownLeft } from 'lucide-react';

const KEY =
    'inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-[5px] border bg-muted px-1 text-xs text-muted-foreground';

/** The keys the palette answers to, under its results (pantalla Paleta). Hidden on a phone, which has no keyboard. */
export function CommandPaletteFooter() {
    return (
        <div
            aria-hidden
            className="hidden items-center gap-4 border-t bg-muted px-4 py-2.5 text-[13px] leading-[18px] text-muted-foreground sm:flex"
        >
            <span className="flex items-center gap-1.5">
                <kbd className={KEY}>
                    <ArrowUp className="size-3" />
                </kbd>
                <kbd className={KEY}>
                    <ArrowDown className="size-3" />
                </kbd>
                moverte
            </span>
            <span className="flex items-center gap-1.5">
                <kbd className={KEY}>
                    <CornerDownLeft className="size-3" />
                </kbd>
                abrir
            </span>
            <span className="ml-auto flex items-center gap-1.5">
                <kbd className={KEY}>⌘K</kbd>
                abrir desde cualquier pantalla
            </span>
        </div>
    );
}
